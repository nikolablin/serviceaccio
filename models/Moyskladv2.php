<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\services\support\Log;

class MoyskladV2 extends Model
{
    private string $login;
    private string $password;

    public function __construct($config = [])
    {
        parent::__construct($config);

        $cfg = Yii::$app->params['moyskladv2'] ?? [];
        $this->login    = (string)($cfg['login'] ?? '');
        $this->password = (string)($cfg['password'] ?? '');
    }

    public function getHrefData(string $href): ?object
    {
        $resp = $this->request('GET', $href);

        if ($resp['ok'] && is_object($resp['data'])) {
            return $resp['data'];
        }

        return null;
    }

    public function buildStateMeta(string $entity, string $stateId): array
    {
        return [
            'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/{$entity}/metadata/states/{$stateId}",
            'type'      => 'state',
            'mediaType' => 'application/json',
        ];
    }

    private function buildEntityMeta(string $entity, string $id): array
    {
        return [
            'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/{$entity}/{$id}",
            'type'      => $entity,
            'mediaType' => 'application/json',
        ];
    }

    public function buildAttributeMeta(string $entity, string $attrId): array
    {
        return [
            'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/{$entity}/metadata/attributes/{$attrId}",
            'type'      => 'attributemetadata',
            'mediaType' => 'application/json',
        ];
    }

    public function request(string $method, string $urlOrPath, ?array $json = null): array
    {
        $isFullUrl = str_starts_with($urlOrPath, 'http://') || str_starts_with($urlOrPath, 'https://');
        $url = $isFullUrl
            ? $urlOrPath
            : 'https://api.moysklad.ru/api/remap/1.2/' . ltrim($urlOrPath, '/');

        $ch = curl_init();

        $headers = [
            'Authorization: Basic ' . base64_encode($this->login . ':' . $this->password),
            'Accept-Encoding: gzip',
            'Connection: Keep-Alive',
        ];

        if ($json !== null) {
            $payload = json_encode($json, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        } else {
            $payload = null;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        return [
            'ok'   => ($code >= 200 && $code < 300),
            'code' => $code,
            'err'  => $err ?: null,
            'raw'  => $raw,
            'data' => $data,
            'url'  => $url,
        ];
    }

    public function updateEntityState(string $entity, string $entityId, array $stateMeta)
    {
        $url = "https://api.moysklad.ru/api/remap/1.2/entity/{$entity}/{$entityId}";

        $payload = [
            'state' => [
                'meta' => $stateMeta
            ]
        ];

        return $this->request('PUT', $url, $payload);
    }

    public function getCustomerOrder(string $id): ?object
    {
        $path = "entity/customerorder/{$id}";
        $path = $this->addExpand($path, $this->expandFor('orders'));

        $resp = $this->request('GET', $path);
        return ($resp['ok'] && is_object($resp['data'])) ? $resp['data'] : null;
    }

    public function getDemand(string $id): ?object
    {
        $path = "entity/demand/{$id}";
        $path = $this->addExpand($path, $this->expandFor('demands'));

        $resp = $this->request('GET', $path);
        return ($resp['ok'] && is_object($resp['data'])) ? $resp['data'] : null;
    }

    public function extractCustomerOrderIdFromDemand(object $demand): ?string
    {
        $href = $demand->customerOrder->meta->href ?? null;
        if (!$href || !is_string($href)) {
            return null;
        }
        return basename($href);
    }

    private function addExpand(string $path, ?string $expand): string
    {
        if (!$expand) return $path;
        $sep = str_contains($path, '?') ? '&' : '?';
        return $path . $sep . 'expand=' . rawurlencode($expand);
    }

    private function expandFor(string $entity): ?string
    {
        $cfg = Yii::$app->params['moyskladv2'][$entity]['expand'] ?? null;
        return $cfg ? (string)$cfg : null;
    }

    public function buildOrderPatch(object $order, object $config): array
    {
        $payload = [];
        $changed = [];

        // локальные мини-хелперы (не плодим методы по классу)
        $entityMeta = function (string $entity, string $id): array {
            return [
                'meta' => [
                    'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/{$entity}/{$id}",
                    'type'      => $entity,
                    'mediaType' => 'application/json',
                ],
            ];
        };

        $stateMeta = function (string $entity, string $stateId): array {
            return [
                'meta' => [
                    'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/{$entity}/metadata/states/{$stateId}",
                    'type'      => 'state',
                    'mediaType' => 'application/json',
                ],
            ];
        };

        // -------- 1) state (status) --------
        if (!empty($config->status) && $config->status != 'byhand') {
            $cur = $order->state->meta->href ?? null;
            $need = "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/{$config->status}";

            if ($cur !== $need) {
                $payload['state'] = $stateMeta('customerorder', (string)$config->status);
                $changed[] = 'state';
            }
        }

        // -------- 2) organization --------
        if (!empty($config->organization)) {
            $cur = $order->organization->meta->href ?? null;
            $need = "https://api.moysklad.ru/api/remap/1.2/entity/organization/{$config->organization}";

            if ($cur !== $need) {
                $payload['organization'] = $entityMeta('organization', (string)$config->organization);
                $changed[] = 'organization';
            }
        }

        // -------- 3) legal_account (organizationAccount) --------
        if (!empty($config->organization) && !empty($config->legal_account)) {
            $cur = $order->organizationAccount->meta->href ?? null;
            $need = "https://api.moysklad.ru/api/remap/1.2/entity/organization/{$config->organization}/accounts/{$config->legal_account}";
            if ($cur !== $need) {
                $payload['organizationAccount'] = [
                    'meta' => [
                        'href'      => $need,
                        'type'      => 'account',
                        'mediaType' => 'application/json',
                    ],
                ];
                $changed[] = 'organizationAccount';
            }
        }

        // -------- 4) attributes --------
        $attrIds = Yii::$app->params['moyskladv2']['orders']['attributesFields'] ?? [];

        // текущие значения атрибутов заказа (map attrId => valueId|bool|string)

        // helper: добавить атрибут в payload (накапливаем список)
        $setAttr = function (string $attrId, $value) use (&$payload) {
            if (!isset($payload['attributes'])) $payload['attributes'] = [];

            $payload['attributes'][] = [
                'meta' => [
                    'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/{$attrId}",
                    'type'      => 'attributemetadata',
                    'mediaType' => 'application/json',
                ],
                'value' => $value,
            ];
        };

        // справочниковые атрибуты (значение = customentity meta на valueId)
        $refAttr = function (?string $attrId, $valueId, string $label)
            use ($order, $entityMeta, $setAttr, &$changed)
        {
            if (!$attrId) return;
            if ($valueId === null) return;

            // byhand — пропускаем полностью
            if ($valueId === 'byhand') return;

            $cur = $this->getAttributeValue($order, (string)$attrId);
            if ((string)$cur === (string)$valueId) return;

            $setAttr((string)$attrId, $entityMeta('customentity', (string)$valueId));
            $changed[] = "attr:{$label}";
        };

        $refAttr($attrIds['paymentType']   ?? null, $config->payment_type    ?? null, 'paymentType');
        $refAttr($attrIds['channel']       ?? null, $config->channel         ?? null, 'channel');
        $refAttr($attrIds['project']       ?? null, $config->project_field   ?? null, 'project');
        $refAttr($attrIds['paymentStatus'] ?? null, $config->payment_status  ?? null, 'paymentStatus');
        $refAttr($attrIds['delivery']      ?? null, $config->delivery_service?? null, 'delivery');
        $refAttr($attrIds['fiskal']        ?? null, $config->fiscal          ?? null, 'fiskal');

        return ['payload' => $payload, 'changed' => $changed];
    }

    public function getAttributeValue(object $entity, string $attributeId)
    {
        $attrs = $entity->attributes ?? null;
        if (!is_array($attrs)) {
            return null;
        }

        foreach ($attrs as $a) {
            if ((string)($a->id ?? '') !== (string)$attributeId) {
                continue;
            }

            $value = $a->value ?? null;

            // 1) Справочник → UUID значения
            if (is_object($value)) {
                $href = $value->meta->href ?? null;
                if ($href && is_string($href)) {
                    return basename($href);
                }
            }

            // 2) boolean
            if (is_bool($value)) {
                return $value;
            }

            // 3) string / int / float
            if (is_scalar($value)) {
                return $value;
            }

            return null;
        }

        return null;
    }

    private function findEntityIdsByFilterHref(string $entity, string $field, string $orderHref): array
    {
        $filter = rawurlencode("{$field}={$orderHref}");
        $limit  = 100;
        $offset = 0;

        $ids = [];

        while (true) {
            $resp = $this->request('GET', "entity/{$entity}?limit={$limit}&offset={$offset}&filter={$filter}");

            if (!($resp['ok'] ?? false) || !is_object($resp['data'])) break;

            $rows = $resp['data']->rows ?? null;
            if (!is_array($rows) || empty($rows)) break;

            foreach ($rows as $row) {
                if (!empty($row->id)) $ids[] = (string)$row->id;
            }

            if (count($rows) < $limit) break;

            $offset += $limit;
            if ($offset > 5000) break; // защита
        }

        return array_values(array_unique($ids));
    }

    public function deleteLinkedDocsForOrder(object $order, array $entities): array
    {
        $orderId   = (string)($order->id ?? '');
        $orderHref = "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$orderId}";

        $result = [];

        foreach ($entities as $entity => $field) {

            // 1) demand берём прямо из order->demands (без поиска)
            if ($entity === 'demand') {
                $ids = $this->extractDemandIdsFromOrder($order);
            } else {
                // 2) остальное ищем через API filter
                $ids = $this->findEntityIdsByFilterHref($entity, $field, $orderHref);
            }

            $result[$entity] = [];
            foreach ($ids as $id) {
              $result[$entity] = [
                'id'  => $id,
                'res' => $this->deleteEntity($entity, $id),
              ];
            }
        }

        return $result;
    }

    public function deleteEntity(string $entity, string $id): array
    {
        $resp = $this->request('DELETE', "entity/{$entity}/{$id}");

        // если уже удалено — считаем ок
        if (($resp['code'] ?? null) === 404) {
            $resp['ok'] = true;
            $resp['alreadyDeleted'] = true;
        }

        return $resp;
    }

    private function extractDemandIdsFromOrder(object $order): array
    {
        $ids = [];
        $demands = $order->demands ?? null;

        if (!is_array($demands)) return $ids;

        foreach ($demands as $d) {
            $href = $d->meta->href ?? null;
            if ($href && is_string($href)) {
                $ids[] = basename($href);
            }
        }

        // на всякий, чтобы не было дублей
        return array_values(array_unique($ids));
    }

    public function ensureDemandFromOrder($order, $demand, array $options = [])
    {
        $demandHref = null;
        $demandId = null;
        if($demand){
          $demandHref = $demand->meta->href;
          $demandId = $demand->id;
        }

        $payload  = $this->buildDemandPayloadFromOrder($order);
        $map      = Yii::$app->params['moyskladv2']['demands']['upsertDemandAttributes'];

        if (!$demandId) {
            // CREATE
            $payload['state']       = ['meta' => $this->buildStateMeta('demand', $options['state'])];
            $payload['attributes']  = $this->buildDemandAttributesFromOrder($order, null, $map, [ 'clear_missing' => false ]);
            $payload['attributes'][] = [
              'meta' => $this->buildAttributeMeta('demand',Yii::$app->params['moyskladv2']['demands']['attributesFields']['numPlaces']),
              'value' => '1'
            ];
            // + позиции (как ты уже делаешь)
            $payload['positions'] = $this->buildDemandPositionsFromOrderPositions(
                $this->getCustomerOrderPositions($order)
            );

            return $this->request('POST', 'entity/demand', $payload);
        }

        // UPDATE
        $payload['attributes'] = $this->buildDemandAttributesFromOrder($order, $demand, $map, [ 'clear_missing' => false ]);

        $this->request('PUT', "entity/demand/{$demandId}", $payload);

        $this->syncDemandPositionsDiff(
            $demand,
            $this->buildDemandPositionsFromOrderPositions(
                $this->getCustomerOrderPositions($order)
            ),
            $options
        );

        return $this->getDemand($demandId);
    }

    private function findAttributeValueByMetaId(?array $attributes, string $attrMetaId)
    {
        if (!$attributes) return null;

        foreach ($attributes as $a) {
            $href = $a->meta->href ?? null;
            if (!$href) continue;

            $id = basename($href);
            if ($id === $attrMetaId) {
                return $a->value ?? null;
            }
        }

        // специальный маркер: "не найдено"
        return '__ATTR_NOT_FOUND__';
    }

    private function buildDemandAttributesFromOrder(object $order, ?object $demand, array $map, array $options = []): array
    {
        $clearMissing = (bool)($options['clear_missing'] ?? false);

        // 1) Начинаем с текущих атрибутов demand, чтобы не потерять "чужие"
        $resultByDemandAttrId = [];

        $existing = $demand->attributes ?? null;
        if (is_array($existing)) {
            foreach ($existing as $a) {
                $href = $a->meta->href ?? null;
                if (!$href) continue;
                $demandAttrId = basename($href);

                $resultByDemandAttrId[$demandAttrId] = [
                    'meta'  => $this->buildAttributeMeta('demand', $demandAttrId),
                    'value' => $a->value ?? null,
                ];
            }
        }

        // 2) Накатываем значения из заказа по мапе
        $orderAttrs = is_array($order->attributes ?? null) ? $order->attributes : [];

        foreach ($map as $orderAttrId => $demandAttrId) {
            $val = $this->findAttributeValueByMetaId($orderAttrs, (string)$orderAttrId);

            if ($val === '__ATTR_NOT_FOUND__') {
                if ($clearMissing) {
                    // сбросить в demand
                    $resultByDemandAttrId[(string)$demandAttrId] = [
                        'meta'  => $this->buildAttributeMeta('demand', (string)$demandAttrId),
                        'value' => null,
                    ];
                }
                continue;
            }

            // переносим value как есть
            $resultByDemandAttrId[(string)$demandAttrId] = [
                'meta'  => $this->buildAttributeMeta('demand', (string)$demandAttrId),
                'value' => $val,
            ];
        }

        // 3) Возвращаем в формате массива
        return array_values($resultByDemandAttrId);
    }

    private function buildDemandPayloadFromOrder(object $order): array
    {
        $payload = [];

        // Связь с заказом
        $payload['customerOrder'] = ['meta' => $order->meta];

        // Контрагент
        if (!empty($order->agent->meta)) {
            $payload['agent'] = ['meta' => $order->agent->meta];
        }

        // Владелец
        if (!empty($order->owner->meta)) {
            $payload['owner'] = ['meta' => $order->owner->meta];
        }

        // Организация и счёт организации
        if (!empty($order->organization->meta)) {
            $payload['organization'] = ['meta' => $order->organization->meta];
        }
        if (!empty($order->organizationAccount->meta)) {
            $payload['organizationAccount'] = ['meta' => $order->organizationAccount->meta];
        }

        // Склад
        if (!empty($order->store->meta)) {
            $payload['store'] = ['meta' => $order->store->meta];
        }

        // Проект (если используете)
        if (!empty($order->project->meta)) {
            $payload['project'] = ['meta' => $order->project->meta];
        }

        // Комментарий / описание (по желанию)
        if (isset($order->description) && $order->description !== '') {
            $payload['description'] = (string)$order->description;
        }

        // Адрес доставки
        if (isset($order->shipmentAddress) && $order->shipmentAddress !== '') {
            $payload['shipmentAddress'] = (string)$order->shipmentAddress;
        }

        return array_filter($payload, static fn($v) => $v !== null);
    }


    /* ------------- Формирование счета на оплату ------------- */

    public function getInvoiceOut(string $id): ?object
    {
        $path = "entity/invoiceout/{$id}";
        // если хочешь expand отдельно — заведи в params moyskladv2['invoicesout']['expand']
        $resp = $this->request('GET', $path);
        return ($resp['ok'] && is_object($resp['data'])) ? $resp['data'] : null;
    }

    public function ensureInvoiceOutFromOrder(object $order, ?object $invoiceOut, array $options = [])
    {
        $invoiceId = $invoiceOut->id ?? null;

        $payload = $this->buildInvoiceOutPayloadFromOrder($order);

        // state опционально
        $stateId = $options['state'] ?? null;
        if ($stateId) {
            $payload['state'] = ['meta' => $this->buildStateMeta('invoiceout', (string)$stateId)];
        }

        // позиции из заказа
        $desiredPositions = $this->buildInvoiceOutPositionsFromOrderPositions(
            $this->getCustomerOrderPositions($order)
        );

        if (!$invoiceId) {
            // CREATE
            $payload['positions'] = [
                'rows' => $desiredPositions,
            ];

            return $this->request('POST', 'entity/invoiceout', $payload);
        }

        // UPDATE (сначала "шапку")
        $this->request('PUT', "entity/invoiceout/{$invoiceId}", $payload);

        // затем дифф позиций
        $fresh = $this->getInvoiceOut((string)$invoiceId);
        if ($fresh) {
            $this->syncInvoiceOutPositionsDiff($fresh, $desiredPositions);
            return $this->getInvoiceOut((string)$invoiceId);
        }

        // если почему-то не смогли перечитать
        return $this->getInvoiceOut((string)$invoiceId);
    }

    private function buildInvoiceOutPayloadFromOrder(object $order): array
    {
        $payload = [];

        // связь с заказом
        if (!empty($order->meta)) {
            $payload['customerOrder'] = ['meta' => $order->meta];
        }

        // контрагент
        if (!empty($order->agent->meta)) {
            $payload['agent'] = ['meta' => $order->agent->meta];
        }

        // организация и счет
        if (!empty($order->organization->meta)) {
            $payload['organization'] = ['meta' => $order->organization->meta];
        }
        if (!empty($order->organizationAccount->meta)) {
            $payload['organizationAccount'] = ['meta' => $order->organizationAccount->meta];
        }

        // склад
        if (!empty($order->store->meta)) {
            $payload['store'] = ['meta' => $order->store->meta];
        }

        // проект
        if (!empty($order->project->meta)) {
            $payload['project'] = ['meta' => $order->project->meta];
        }

        // комментарий/описание (по желанию)
        if (isset($order->description) && $order->description !== '') {
            $payload['description'] = (string)$order->description;
        }

        return array_filter($payload, static fn($v) => $v !== null);
    }

    private function buildInvoiceOutPositionsFromOrderPositions(array $orderRows): array
    {
        $positions = [];

        foreach ($orderRows as $p) {
            if (empty($p->assortment->meta)) {
                continue;
            }

            $row = [
                'assortment' => ['meta' => $p->assortment->meta],
                'quantity'   => (float)($p->quantity ?? 0),
            ];

            // финполя (если важны)
            if (isset($p->price))      { $row['price'] = (int)$p->price; }
            if (isset($p->discount))   { $row['discount'] = (float)$p->discount; }
            if (isset($p->vat))        { $row['vat'] = (int)$p->vat; }
            if (isset($p->vatEnabled)) { $row['vatEnabled'] = (bool)$p->vatEnabled; }
            if (isset($p->reserve))    { $row['reserve'] = (float)$p->reserve; }

            $positions[] = $row;
        }

        return $positions;
    }

    public function syncInvoiceOutPositionsDiff(object $invoiceOut, array $desiredPositions): void
    {
        $invoiceId = (string)($invoiceOut->id ?? '');
        if (!$invoiceId) return;

        // 1) текущие позиции invoiceout
        $existingRows = [];
        if (isset($invoiceOut->positions->rows) && is_array($invoiceOut->positions->rows)) {
            $existingRows = $invoiceOut->positions->rows;
        } elseif (isset($invoiceOut->positions->meta->href)) {
            $data = $this->getHrefData($invoiceOut->positions->meta->href);
            if ($data && isset($data->rows) && is_array($data->rows)) {
                $existingRows = $data->rows;
            }
        }

        // 2) нормализация
        $existingMap = $this->mapDemandRowsByAssortment($existingRows);     // можно переиспользовать твои методы
        $desiredMap  = $this->mapDesiredRowsByAssortment($desiredPositions);

        // 3) дифф
        $toDelete = [];
        $toAdd    = [];
        $toUpdate = [];

        foreach ($existingMap as $key => $ex) {
            if (!isset($desiredMap[$key])) {
                $toDelete[] = $ex['id'];
                continue;
            }

            $need = $desiredMap[$key];
            $diff = $this->diffPositionFields($ex['row'], $need);

            if ($diff !== null) {
                $toUpdate[] = [
                    'id'      => $ex['id'],
                    'payload' => $diff,
                ];
            }
        }

        foreach ($desiredMap as $key => $need) {
            if (!isset($existingMap[$key])) {
                $toAdd[] = $need;
            }
        }

        // 4) применение
        foreach ($toDelete as $posId) {
            $this->request('DELETE', "entity/invoiceout/{$invoiceId}/positions/{$posId}");
        }

        foreach ($toUpdate as $u) {
            $posId = (string)$u['id'];
            $payload = (array)$u['payload'];
            $this->request('PUT', "entity/invoiceout/{$invoiceId}/positions/{$posId}", $payload);
        }

        $chunks = array_chunk($toAdd, 100);
        foreach ($chunks as $chunk) {
            $this->request('POST', "entity/invoiceout/{$invoiceId}/positions", [
                'rows' => $chunk,
            ]);
        }
    }

    /* ------------- EOF Формирование счета на оплату ------------- */


    /* Формаирование счет-фактуры */

    public function ensureFactureoutFromDemand(object $demand, array $options = [])
    {
      $demandId = basename((string)($demand->meta->href ?? ''));
      if (!$demandId) {
        Log::demandUpdate("ensureFactureoutFromDemand: demand has no meta->href");
        return false;
      }

      $demandHref = "https://api.moysklad.ru/api/remap/1.2/entity/demand/{$demandId}";

      $sum = (int)($options['sum'] ?? ($demand->sum ?? 0));
      if ($sum <= 0) {
        Log::demandUpdate("ensureFactureoutFromDemand: sum <= 0 demand={$demandId}");
        return false;
      }

      $agentMeta    = $demand->agent->meta ?? null;
      $orgMeta      = $demand->organization->meta ?? null;
      $projectMeta  = $demand->project->meta ?? null;
      $ownerMeta    = $demand->owner->meta ?? null;

      if (!$agentMeta || !$orgMeta || !$projectMeta || !$ownerMeta) {
        // пробуем догрузить demand полностью
        $fresh = $this->getHrefData($demandHref);
        if ($fresh) {
          $demand       = $fresh;
          $agentMeta    = $demand->agent->meta ?? null;
          $orgMeta      = $demand->organization->meta ?? null;
          $projectMeta  = $demand->project->meta ?? null;
          $ownerMeta    = $demand->owner->meta ?? null;
        }
      }

      if (!$agentMeta || !$orgMeta || !$projectMeta || !$ownerMeta) {
        Log::demandUpdate("ensureFactureoutFromDemand: demand missing agent/organization/project/owner demand={$demandId}");
        return false;
      }

      $stateId = (string)($options['stateId'] ?? '');
      $stateMeta = $stateId ? $this->buildStateMeta('factureout', $stateId) : null;

      // 1) Пытаемся найти уже привязанную счет-фактуру из demand (если МС отдает такое поле)
      $existingHref = $this->extractLinkedFactureoutHrefFromDemand($demand);

      $payload = [
      'organization' => ['meta' => $orgMeta],
      'agent'        => ['meta' => $agentMeta],
      'project'      => ['meta' => $projectMeta],
      'owner'        => ['meta' => $ownerMeta],
      'sum'          => $sum,
      'demands' => [
          [
            'meta' => [
              'href'      => $demandHref,
              'type'      => 'demand',
              'mediaType' => 'application/json',
            ],
          ]
        ],
      ];

      if (array_key_exists('moment', $options) && $options['moment']) {
        $payload['moment'] = (string)$options['moment'];
      }

      if (array_key_exists('applicable', $options)) {
        $payload['applicable'] = (bool)$options['applicable'];
      }

      if ($stateMeta) {
        $payload['state'] = ['meta' => $stateMeta];
      }

      // Если найдена существующая — обновим, иначе создадим
      try {
        if ($existingHref) {
          $id = basename($existingHref);

          $res = $this->request('PUT', "entity/factureout/{$id}", $payload);

          if (!$res) {
            Log::demandUpdate("ensureFactureoutFromDemand: PUT failed id={$id} demand={$demandId}");
            return false;
          }
          return is_object($res) ? $res : (object)$res;
        }

        $res = $this->request('POST', "entity/factureout", $payload);
        if (!$res) {
          Log::demandUpdate("ensureFactureoutFromDemand: POST failed demand={$demandId}");
          return false;
        }

        return is_object($res) ? $res : (object)$res;

      } catch (\Throwable $e) {
        Log::demandUpdate("ensureFactureoutFromDemand: exception demand={$demandId} msg=" . $e->getMessage());
        return false;
      }
    }

    /**
     * Пытается вытащить href привязанного factureOut прямо из объекта demand, если поле доступно.
     * Возвращает href или ''.
     */
    private function extractLinkedFactureoutHrefFromDemand(object $demand): string
    {
        // Иногда в API могут прилетать связки вида payments[] с meta->type
        if (isset($demand->factureOut) && is_object($demand->factureOut)) {
          $f = $demand->factureOut;
          if (!empty($f->meta->href)) {
              return (string)$f->meta->href;
          }
        }

        return '';
    }

    /* EOF Формаирование счет-фактуры */

    /* Формирование входящего платежа */

    public function ensureMoneyInFromDemand(object $demand, string $type, array $options = [])
    {
      $type = trim(strtolower($type));
      if (!in_array($type, ['paymentin', 'cashin'], true)) {
        Log::demandUpdate("ensureMoneyInFromDemand: invalid type={$type}");
        return false;
      }

      $demandId = basename((string)($demand->meta->href ?? ''));
      if (!$demandId) {
        Log::demandUpdate("ensureMoneyInFromDemand: demand has no meta->href");
        return false;
      }

      $demandHref = "https://api.moysklad.ru/api/remap/1.2/entity/demand/{$demandId}";

      $sum = (int)($options['sum'] ?? ($demand->sum ?? 0));
      if ($sum <= 0) {
        Log::demandUpdate("ensureMoneyInFromDemand: sum <= 0 demand={$demandId}");
        return false;
      }

      $agentMeta      = $demand->agent->meta ?? null;
      $orgMeta        = $demand->organization->meta ?? null;
      $orgAccountMeta = $demand->organizationAccount->meta ?? null;
      $projectMeta    = $demand->project->meta ?? null;
      $ownerMeta      = $demand->owner->meta ?? null;

      if (!$agentMeta || !$orgMeta || !$projectMeta || !$ownerMeta || $orgAccountMeta) {
        // пробуем догрузить demand полностью
        $fresh = $this->getHrefData($demandHref);
        if ($fresh) {
          $demand = $fresh;
          $agentMeta      = $demand->agent->meta ?? null;
          $orgMeta        = $demand->organization->meta ?? null;
          $orgAccountMeta = $demand->organizationAccount->meta ?? null;
          $projectMeta    = $demand->project->meta ?? null;
          $ownerMeta      = $demand->owner->meta ?? null;
        }
      }

      if (!$agentMeta || !$orgMeta || !$projectMeta || !$ownerMeta || !$orgAccountMeta) {
        Log::demandUpdate("ensureMoneyInFromDemand: demand missing agent/organization/project/owner demand={$demandId}", [ 'agentMeta' => $agentMeta, 'orgMeta' => $orgMeta, 'projectMeta' => $projectMeta, 'ownerMeta' => $ownerMeta, 'orgAccountMeta' => $orgAccountMeta ]);
        return false;
      }

      $stateId = (string)($options['stateId'] ?? '');
      $stateMeta = $stateId ? $this->buildStateMeta($type, $stateId) : null;

      // 1) Пытаемся найти уже привязанный платеж из demand (если МС отдает такое поле)
      // В разных конфигурациях это может быть payments / paymentIn / cashIn — поэтому делаем мягко.
      $existingHref = $this->extractLinkedMoneyInHrefFromDemand($demand, $type);

      // 2) Fallback: поиск по фильтру, что в operations присутствует demandHref.
      // Если фильтр не сработает в вашей МС — просто не найдем и создадим новый.
      if (!$existingHref) {
        $existingHref = $this->findMoneyInHrefByDemandHref($type, $demandHref);
      }

      $payload = [
        'organization'        => ['meta' => $orgMeta],
        'organizationAccount' => ['meta' => $orgAccountMeta],
        'agent'               => ['meta' => $agentMeta],
        'project'             => ['meta' => $projectMeta],
        'owner'               => ['meta' => $ownerMeta],
        'sum'                 => $sum,
        'operations' => [
            [
              'meta'      => ['href' => $demandHref, 'type' => 'demand', 'mediaType' => 'application/json'],
              'linkedSum' => $sum,
            ]
          ],
      ];

      if (array_key_exists('moment', $options) && $options['moment']) {
        $payload['moment'] = (string)$options['moment'];
      }

      if (array_key_exists('applicable', $options)) {
        $payload['applicable'] = (bool)$options['applicable'];
      }

      if ($stateMeta) {
        $payload['state'] = ['meta' => $stateMeta];
      }

      if (!empty($options['attributes']) && is_array($options['attributes'])) {
        $payload['attributes'] = $this->normalizeAttributesForEntity($type, $options['attributes']);
      }
      // Если найден существующий — обновим, иначе создадим
      try {
        if ($existingHref) {
          $id = basename($existingHref);

          $res = $this->request('PUT', "entity/{$type}/{$id}", $payload);
          if (!$res) {
            Log::demandUpdate("ensureMoneyInFromDemand: PUT failed type={$type} id={$id} demand={$demandId}");
            return false;
          }
          return is_object($res) ? $res : (object)$res;
        }

        $res = $this->request('POST', "entity/{$type}", $payload);

        if (!$res) {
          Log::demandUpdate("ensureMoneyInFromDemand: POST failed type={$type} demand={$demandId}");
          return false;
        }

        return is_object($res) ? $res : (object)$res;

      } catch (\Throwable $e) {
        Log::demandUpdate("ensureMoneyInFromDemand: exception type={$type} demand={$demandId} msg=" . $e->getMessage());
        return false;
      }
    }


    private function normalizeAttributesForEntity(string $entity, array $attrs): array
    {
        $rows = [];

        foreach ($attrs as $attrId => $item) {
            $attrId = trim((string)$attrId);
            if ($attrId === '') { continue; }

            // backward compatible: если передали просто value
            if (!is_array($item) || !array_key_exists('value', $item)) {
                $rows[] = [
                    'meta'  => $this->buildAttributeMeta($entity, $attrId),
                    'value' => $item,
                ];
                continue;
            }

            $type  = strtolower(trim((string)($item['type'] ?? 'string')));
            $value = $item['value'];

            if ($type === 'customentity') {
                $dictId = trim((string)($item['dictionary'] ?? ''));
                $uuid   = trim((string)$value);

                if ($dictId === '' || $uuid === '') {
                    Log::orderUpdate("normalizeAttributesForEntity: customentity missing dictionary/value entity={$entity} attr={$attrId}");
                    continue; // лучше пропустить, чем отправить мусор
                }

                $href = "https://api.moysklad.ru/api/remap/1.2/entity/customentity/{$dictId}/{$uuid}";

                $rows[] = [
                    'meta' => $this->buildAttributeMeta($entity, $attrId),
                    'value' => [
                        'meta' => [
                            'href'      => $href,
                            'type'      => 'customentity',
                            'mediaType' => 'application/json',
                        ],
                    ],
                ];
                continue;
            }

            if ($type === 'boolean') {
                // допускаем: true/false, 1/0, "1"/"0", "true"/"false", "yes"/"no"
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($bool === null) {
                    Log::orderUpdate("normalizeAttributesForEntity: boolean invalid entity={$entity} attr={$attrId}");
                    continue;
                }

                $rows[] = [
                    'meta'  => $this->buildAttributeMeta($entity, $attrId),
                    'value' => $bool,
                ];
                continue;
            }

            // по умолчанию — строка/скаляр (и bool/int тоже пройдут)
            $rows[] = [
                'meta'  => $this->buildAttributeMeta($entity, $attrId),
                'value' => $value,
            ];
        }

        return $rows;
    }

    /**
     * Пытается вытащить href привязанного paymentin/cashin прямо из объекта demand, если поле доступно.
     * Возвращает href или ''.
     */
    private function extractLinkedMoneyInHrefFromDemand(object $demand, string $type): string
    {
        // Иногда в API могут прилетать связки вида payments[] с meta->type
        if (!empty($demand->payments) && is_array($demand->payments)) {
            foreach ($demand->payments as $p) {
                $t = (string)($p->meta->type ?? '');
                if ($t === $type && !empty($p->meta->href)) {
                    return (string)$p->meta->href;
                }
            }
        }

        // Иногда могут быть поля paymentIn / cashIn
        if ($type === 'paymentin' && !empty($demand->paymentIn->meta->href)) {
            return (string)$demand->paymentIn->meta->href;
        }
        if ($type === 'cashin' && !empty($demand->cashIn->meta->href)) {
            return (string)$demand->cashIn->meta->href;
        }

        return '';
    }

    /**
     * Fallback-поиск paymentin/cashin по demandHref.
     * В МС фильтр может работать/не работать — если не сработает, вернет '' и мы создадим новый платеж.
     */
    private function findMoneyInHrefByDemandHref(string $type, string $demandHref): string
    {
        // Пробуем фильтр по operations. В МС иногда фильтры принимают meta-href как значение.
        // Пример (идея): /entity/paymentin?filter=operations=https://.../demand/ID
        try {
            $encoded = rawurlencode($demandHref);
            $path = "{$type}?filter=operations={$encoded}&limit=1";
            $res = $this->request('GET', $path);

            if (is_object($res) && !empty($res->rows) && is_array($res->rows)) {
                $row = $res->rows[0] ?? null;
                if ($row && !empty($row->meta->href)) {
                    return (string)$row->meta->href;
                }
            }
        } catch (\Throwable $e) {
            \app\services\Log::orderUpdate("findMoneyInHrefByDemandHref: exception type={$type} msg=" . $e->getMessage());
        }

        return '';
    }

    /* EOF Формирование входящего платежа */


    /*  Формирование возврата покупателя */

    public function ensureSalesReturnFromDemand(object $demand, ?object $salesreturn = null, array $options = []): array
    {
        $demandId = basename((string)($demand->meta->href ?? $demand->id ?? ''));
        if ($demandId === '') {
            Log::demandUpdate("ensureSalesReturnFromDemand: demand has no id/meta->href");
            return ['ok'=>false,'code'=>0,'err'=>'no_demand_id'];
        }
        $demandHref = "https://api.moysklad.ru/api/remap/1.2/entity/demand/{$demandId}";

        // подтягиваем базовые meta
        $agentMeta   = $demand->agent->meta ?? null;
        $orgMeta     = $demand->organization->meta ?? null;
        $storeMeta   = $demand->store->meta ?? null;
        $projectMeta = $demand->project->meta ?? null;
        $ownerMeta   = $demand->owner->meta ?? null;

        if (!$agentMeta || !$orgMeta || !$storeMeta || !$ownerMeta) {
            $fresh = $this->getDemand($demandId);
            if ($fresh) {
                $demand = $fresh;
                $agentMeta   = $demand->agent->meta ?? null;
                $orgMeta     = $demand->organization->meta ?? null;
                $storeMeta   = $demand->store->meta ?? null;
                $projectMeta = $demand->project->meta ?? null;
                $ownerMeta   = $demand->owner->meta ?? null;
            }
        }

        if (!$agentMeta || !$orgMeta || !$storeMeta || !$ownerMeta) {
            Log::demandUpdate("ensureSalesReturnFromDemand: demand missing meta parts demand={$demandId}");
            return ['ok'=>false,'code'=>0,'err'=>'demand_missing_meta'];
        }

        $stateId   = (string)($options['stateId'] ?? '');
        $stateMeta = $stateId !== '' ? $this->buildStateMeta('salesreturn', $stateId) : null;

        // позиции: берём из demand.positions.rows
        $rows = [];

        if (isset($demand->positions->rows) && is_array($demand->positions->rows)) {
            foreach ($demand->positions->rows as $p) {
                if (empty($p->assortment->meta)) continue;
                if (empty($p->meta->href)) continue; // важно: нужна ссылка на саму позицию demand

                $row = [
                    'assortment' => ['meta' => $p->assortment->meta],
                    'quantity'   => (float)($p->quantity ?? 0),

                    // КЛЮЧЕВОЕ: привязка к строке продажи
                    'demandPosition' => [
                        'meta' => [
                            'href'      => (string)$p->meta->href,
                            'type'      => 'demandposition',
                            'mediaType' => 'application/json',
                        ],
                    ],
                ];

                // (не обязательно, но часто полезно)
                if (isset($p->price)) $row['price'] = (int)$p->price;
                if (isset($p->vat)) $row['vat'] = (int)$p->vat;
                if (isset($p->vatEnabled)) $row['vatEnabled'] = (bool)$p->vatEnabled;

                $rows[] = $row;
            }
        }

        $payload = [
            'organization' => ['meta' => $orgMeta],
            'agent'        => ['meta' => $agentMeta],
            'store'        => ['meta' => $storeMeta],
            'owner'        => ['meta' => $ownerMeta],
        ];

        if ($projectMeta) $payload['project'] = ['meta' => $projectMeta];

        // основание (вариант A): demand
        $payload['demand'] = [
            'meta' => [
                'href'      => $demandHref,
                'type'      => 'demand',
                'mediaType' => 'application/json',
            ],
        ];

        if (!empty($rows)) {
            $payload['positions'] = $rows;
        }

        if (array_key_exists('applicable', $options)) $payload['applicable'] = (bool)$options['applicable'];
        if ($stateMeta) $payload['state'] = ['meta' => $stateMeta];

        if (!empty($options['attributes']) && is_array($options['attributes'])) {
          $payload['attributes'] = $this->normalizeAttributesForEntity('salesreturn', $options['attributes']);
        }

        // если передали существующий salesreturn объект — обновляем его, иначе создаём
        $existingId = null;
        if ($salesreturn && !empty($salesreturn->id)) {
            $existingId = (string)$salesreturn->id;
        }

        if ($existingId) {
            $res = $this->request('PUT', "entity/salesreturn/{$existingId}", $payload);
            return $res;
        }

        $res = $this->request('POST', "entity/salesreturn", $payload);

        return $res;
    }


    /*  EOF Формирование возврата покупателя */


    /* ------------- Формирование пакета товаров из заказа в отгрузку ------------- */

    public function getCustomerOrderPositions(object $order): array
    {
        if (
            isset($order->positions)
            && isset($order->positions->rows)
            && is_array($order->positions->rows)
        ) {
            return $order->positions->rows;
        }

        // 2) Если rows нет, но есть href — грузим через getHrefData
        if (
            isset($order->positions)
            && isset($order->positions->meta)
            && isset($order->positions->meta->href)
        ) {
            $data = $this->getHrefData($order->positions->meta->href);

            if ($data && isset($data->rows) && is_array($data->rows)) {
                return $data->rows;
            }
        }

        // 3) Ничего нет
        return [];
    }

    private function buildDemandPositionsFromOrderPositions(array $orderRows): array
    {
        $positions = [];

        // пул серийников по ассортименту (чтобы "вынимать" и не дублировать)
        $serialPool = [];

        foreach ($orderRows as $p) {
            if (empty($p->assortment->meta)) {
                continue;
            }

            $assortmentId = basename((string)($p->assortment->meta->href ?? ''));
            $isSerialTrackable = (bool)($p->assortment->isSerialTrackable ?? false);

            // Важно: серийники == штуки, значит quantity должен быть целым
            $qtyFloat = (float)($p->quantity ?? 0);
            $qty = (int)round($qtyFloat);

            $row = [
                'assortment' => ['meta' => $p->assortment->meta],
                'quantity'   => $qtyFloat,
            ];

            if (isset($p->price))       { $row['price'] = (int)$p->price; }
            if (isset($p->discount))    { $row['discount'] = (float)$p->discount; }
            if (isset($p->vat))         { $row['vat'] = (int)$p->vat; }
            if (isset($p->vatEnabled))  { $row['vatEnabled'] = (bool)$p->vatEnabled; }
            if (isset($p->reserve))     { $row['reserve'] = (float)$p->reserve; }

            if ($isSerialTrackable) {
                // 1) Если серийники пришли в самой позиции (самый правильный вариант)
                $thingsSrc = null;

                if (!empty($p->things) && is_array($p->things)) {
                    $thingsSrc = $p->things;
                } elseif (!empty($p->assortment->things) && is_array($p->assortment->things)) {
                    // 2) Иначе берем из пула товара, но только qty штук
                    if (!isset($serialPool[$assortmentId])) {
                        $serialPool[$assortmentId] = array_values(
                            array_filter(array_map('strval', $p->assortment->things))
                        );
                    }
                    $thingsSrc = array_splice($serialPool[$assortmentId], 0, max(0, $qty));
                }

                $things = $thingsSrc ? array_values(array_filter(array_map('strval', $thingsSrc))) : [];

                // Важно: things должно строго соответствовать quantity
                if ($qty > 0 && count($things) === $qty) {
                    $row['things'] = $things;
                } else {
                    // если qty не целое или серийников не хватает/слишком много — НЕ шлём things,
                    // иначе отгрузка/списание может отклониться из-за несоответствия
                    // (по желанию можно залогировать)
                    // Log::orderCreate('SERIAL_MISMATCH', ['assortmentId'=>$assortmentId,'qty'=>$qtyFloat,'thingsCount'=>count($things)]);
                }
            }

            $positions[] = $row;
        }

        return $positions;
    }

    /* ------------- EOF Формирование пакета товаров из заказа в отгрузку ------------- */



    /* ------------- Проверка разницы товароы в заказе/отгрузке ------------- */

    public function syncDemandPositionsDiff(object $demand, array $desiredPositions, array $options = []): void
    {
        $demandId = (string)($demand->id ?? '');
        if (!$demandId) {
            return;
        }

        // 1) Текущие позиции demand
        $existingRows = [];
        if (isset($demand->positions->rows) && is_array($demand->positions->rows)) {
            $existingRows = $demand->positions->rows;
        } elseif (isset($demand->positions->meta->href)) {
            $data = $this->getHrefData($demand->positions->meta->href);
            if ($data && isset($data->rows) && is_array($data->rows)) {
                $existingRows = $data->rows;
            }
        }

        // 2) Нормализуем позиции в мапы по ключу
        // Ключ: assortmentHref. Если у тебя бывают дубли одного товара отдельными строками —
        // лучше агрегировать (суммировать quantity). Здесь так и сделано.
        $existingMap = $this->mapDemandRowsByAssortment($existingRows);
        $desiredMap  = $this->mapDesiredRowsByAssortment($desiredPositions);

        // 3) Дифф
        $toDelete = []; // posId[]
        $toAdd    = []; // rows[]
        $toUpdate = []; // [posId => payload]

        foreach ($existingMap as $key => $ex) {
            if (!isset($desiredMap[$key])) {
                $toDelete[] = $ex['id'];
                continue;
            }

            $need = $desiredMap[$key];
            $diff = $this->diffPositionFields($ex['row'], $need);

            if ($diff !== null) {
                $toUpdate[] = [
                    'id'      => $ex['id'],
                    'payload' => $diff,
                ];
            }
        }

        foreach ($desiredMap as $key => $need) {
            if (!isset($existingMap[$key])) {
                $toAdd[] = $need;
            }
        }

        // 4) Применяем изменения
        // Удаление
        foreach ($toDelete as $posId) {
            $this->request('DELETE', "entity/demand/{$demandId}/positions/{$posId}");
        }

        // Обновление (PUT по позиции)
        foreach ($toUpdate as $u) {
            $posId = (string)$u['id'];
            $payload = (array)$u['payload'];
            $this->request('PUT', "entity/demand/{$demandId}/positions/{$posId}", $payload);
        }

        // Добавление (батчами по 100)
        $chunks = array_chunk($toAdd, 100);
        foreach ($chunks as $chunk) {
            $this->request('POST', "entity/demand/{$demandId}/positions", [
                'rows' => $chunk,
            ]);
        }
    }

    /**
     * existing demand rows -> map[key] = ['id'=>posId,'row'=>row]
     */
    private function mapDemandRowsByAssortment(array $rows): array
    {
        $map = [];

        foreach ($rows as $r) {
            $posId = (string)($r->id ?? '');
            $aHref = $r->assortment->meta->href ?? null;
            if (!$posId || !$aHref) continue;

            $key = (string)$aHref;

            // если дубли — агрегируем quantity, остальные поля оставляем как есть
            if (!isset($map[$key])) {
                $map[$key] = [
                    'id'  => $posId,
                    'row' => $r,
                ];
            } else {
                $map[$key]['row']->quantity = (float)($map[$key]['row']->quantity ?? 0) + (float)($r->quantity ?? 0);
            }
        }

        return $map;
    }

    /**
     * desired rows (payload rows) -> map[key] = row
     * ожидается формат row как у твоего buildDemandPositionsFromOrderPositions()
     */
    private function mapDesiredRowsByAssortment(array $rows): array
    {
        $map = [];

        foreach ($rows as $r) {
            // у тебя: 'assortment' => ['meta' => $p->assortment->meta]
            $aHref = $r['assortment']['meta']->href ?? null;
            if (!$aHref) continue;

            $key = (string)$aHref;

            if (!isset($map[$key])) {
                $map[$key] = $r;
            } else {
                // агрегируем quantity
                $map[$key]['quantity'] = (float)($map[$key]['quantity'] ?? 0) + (float)($r['quantity'] ?? 0);
            }
        }

        return $map;
    }

    /**
     * Вернуть payload для PUT позиции, если есть изменения; иначе null.
     * Сравниваем только те поля, которые ты формируешь в desired row.
     */
    private function diffPositionFields(object $existingRow, array $desiredRow): ?array
    {
        $payload = [];

        $exQty = (float)($existingRow->quantity ?? 0);
        $neQty = (float)($desiredRow['quantity'] ?? 0);
        if ($exQty !== $neQty) {
            $payload['quantity'] = $neQty;
        }

        if (array_key_exists('price', $desiredRow)) {
            $ex = (int)($existingRow->price ?? 0);
            $ne = (int)$desiredRow['price'];
            if ($ex !== $ne) $payload['price'] = $ne;
        }

        if (array_key_exists('discount', $desiredRow)) {
            $ex = (float)($existingRow->discount ?? 0);
            $ne = (float)$desiredRow['discount'];
            if ($ex !== $ne) $payload['discount'] = $ne;
        }

        if (array_key_exists('vat', $desiredRow)) {
            $ex = (int)($existingRow->vat ?? 0);
            $ne = (int)$desiredRow['vat'];
            if ($ex !== $ne) $payload['vat'] = $ne;
        }

        if (array_key_exists('vatEnabled', $desiredRow)) {
            $ex = (bool)($existingRow->vatEnabled ?? false);
            $ne = (bool)$desiredRow['vatEnabled'];
            if ($ex !== $ne) $payload['vatEnabled'] = $ne;
        }

        if (array_key_exists('reserve', $desiredRow)) {
            $ex = (float)($existingRow->reserve ?? 0);
            $ne = (float)$desiredRow['reserve'];
            if ($ex !== $ne) $payload['reserve'] = $ne;
        }

        return $payload ? $payload : null;
    }

    /* ------------- EOF Проверка разницы товароы в заказе/отгрузке ------------- */

    /* Подготовка файла и загрузка в МС */

    public function ensureFileFromUrl(string $url, string $entity, string $entityId, string $filename = 'waybill.pdf'): ?array
    {
        $fileData = @file_get_contents($url);
        if ($fileData === false || $fileData === '') {
            Log::demandUpdate('ensureFileFromUrl: file_get_contents failed', [
                'url' => $url, 'entity' => $entity, 'entityId' => $entityId,
            ]);
            return null;
        }

        // ВАЖНО: для /files МС ждёт МАССИВ объектов
        $payload = [[
            'filename'    => $filename,
            'content'     => base64_encode($fileData),
            'description' => 'Накладная Каспи: ' . $url,
        ]];

        $res = $this->request('POST', "entity/{$entity}/{$entityId}/files", $payload);

        // ✅ тут проверяем ТОЛЬКО ok, а не is_object(data)
        if (!($res['ok'] ?? false)) {
          Log::demandUpdate('ensureFileFromUrl: upload failed', [ 'url' => $url, 'entity' => $entity, 'entityId' => $entityId, 'http' => $res['code'] ?? null, 'raw' => $res['raw'] ?? null, ]);
          return null;
        }

        $data = $res['data'] ?? null;

        // ответ часто приходит как ARRAY (как у тебя в логе)
        if (is_array($data) && !empty($data[0]->meta)) {
            return ['meta' => $data[0]->meta];
        }

        // иногда может прийти object с rows
        if (is_object($data) && isset($data->rows) && is_array($data->rows) && !empty($data->rows[0]->meta)) {
            return ['meta' => $data->rows[0]->meta];
        }

        // fallback: не смогли разобрать, но запрос успешный — залогируем формат
        Log::demandUpdate('ensureFileFromUrl: upload ok but cannot parse response', [ 'url' => $url, 'entity' => $entity, 'entityId' => $entityId, 'raw' => $res['raw'] ?? null, ]);

        return null;
    }

    /* EOF Подготовка файла и загрузка в МС */


    /* Работа с товарами */

    public function checkOrganizationVatEnabled($orgId)
    {
      if(in_array($orgId, Yii::$app->params['moyskladv2']['vat']['vatOrganizations'], true)) {
        return true;
      }

      return false;
    }

    public function buildCustomerOrderPositionsVatPatch(object $order, int $vatPercent): array
    {
        $patches = [];

        $positions = $this->getCustomerOrderPositions($order);
        if (!$positions) {
            return $patches;
        }

        foreach ($positions as $pos) {
            $posId = $pos->id ?? null;
            if (!$posId) { continue; }

            $vatEnabled = (bool)($pos->vatEnabled ?? false);
            $vat        = (int)($pos->vat ?? 0);

            if ($vatEnabled === true && $vat === $vatPercent) {
                continue;
            }

            $patches[] = [
                'positionId' => (string)$posId,
                'payload'    => [
                    'vatEnabled' => true,
                    'vat'        => $vatPercent,
                ],
                'before'     => [
                    'vatEnabled' => $vatEnabled,
                    'vat'        => $vat,
                ],
            ];
        }

        return $patches;
    }

    public function applyCustomerOrderPositionsVatPatch(string $orderId, array $patches): array
    {
        $res = [
            'total'     => count($patches),
            'changed'   => 0,
            'errors'    => [],
            'has_error' => false,
        ];

        foreach ($patches as $p) {
            $posId  = $p['positionId'] ?? null;
            $payload = $p['payload'] ?? null;

            if (!$posId || empty($payload)) {
                continue;
            }

            $r = $this->request('PUT', "entity/customerorder/{$orderId}/positions/{$posId}", $payload);

            if (empty($r['ok'])) {
                $res['has_error'] = true;
                $res['errors'][] = [
                    'positionId' => $posId,
                    'code'       => $r['code'] ?? null,
                    'raw'        => $r['raw'] ?? null,
                ];
                // строго: стопаемся сразу
                break;
            }

            $res['changed']++;
        }

        return $res;
    }

    /* EOF Работа с продуктами */
}
