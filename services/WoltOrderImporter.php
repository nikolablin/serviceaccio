<?php

namespace app\services;

use Yii;

class WoltOrderImporter
{
    public function registerEvent(array $payload): bool
    {
        $eventId = (string)($payload['id'] ?? '');
        if ($eventId === '') return true; // если вдруг нет id — не блокируем

        $type   = (string)($payload['type'] ?? '');
        $orderId = (string)($payload['order']['id'] ?? '');
        $status = (string)($payload['order']['status'] ?? '');
        $createdAt = $this->isoToDb((string)($payload['created_at'] ?? ''));

        try {
            Yii::$app->db->createCommand()->insert('{{%wolt_events}}', [
                'event_id'    => $eventId,
                'event_type'  => $type,
                'order_id'    => $orderId,
                'status'      => $status,
                'created_at'  => $createdAt,
                'raw_json'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at_local' => date('Y-m-d H:i:s'),
            ])->execute();

            return true; // новое событие
        } catch (\yii\db\Exception $e) {
            // duplicate key
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                return false; // дубль события
            }
            throw $e;
        }
    }

    /**
     * Upsert заказа + позиции.
     * Возвращает true если записали/обновили, false если решили пропустить (не новее).
     */
    public function upsertOrder(array $order): bool
    {
        $db = Yii::$app->db;

        $orderId = (string)($order['id'] ?? '');
        if ($orderId === '') {
            throw new \RuntimeException('Empty Wolt order id');
        }

        $modifiedAt = $this->isoToDb((string)($order['modified_at'] ?? ''));
        $createdAt  = $this->isoToDb((string)($order['created_at'] ?? ''));
        $pickupEta  = $this->isoToDb((string)($order['pickup_eta'] ?? ''));

        // 1) антидубль по modified_at: если в БД уже есть и не старее — пропускаем
        $existing = $db->createCommand("
            SELECT `modified_at`
            FROM {{%wolt_orders}}
            WHERE `wolt_order_id` = :id
            LIMIT 1
        ", [':id' => $orderId])->queryOne();

        if ($existing && !empty($existing['modified_at']) && $modifiedAt) {
            if (strtotime($modifiedAt) <= strtotime($existing['modified_at'])) {
                return false; // ничего нового
            }
        }

        $basketTotal = (int)($order['basket_price']['total']['amount'] ?? 0);
        $basketCur   = (string)($order['basket_price']['total']['currency'] ?? 'KZT');

        $feesTotal = (int)($order['fees']['total']['amount'] ?? 0);
        $feesCur   = (string)($order['fees']['total']['currency'] ?? 'KZT');

        $deliveryStatus = (string)($order['delivery']['status'] ?? '');
        $deliveryType   = (string)($order['delivery']['type'] ?? '');

        $vat = $this->firstItemVat($order);

        $row = [
            'wolt_order_id' => $orderId,
            'order_number'  => (string)($order['order_number'] ?? null),

            'venue_id'      => (string)($order['venue']['id'] ?? null),
            'venue_name'    => (string)($order['venue']['name'] ?? null),

            'order_status'  => (string)($order['order_status'] ?? null),
            'delivery_type' => $deliveryType ?: null,
            'delivery_status' => $deliveryStatus ?: null,

            'basket_total_amount' => $basketTotal,
            'basket_currency'     => $basketCur ?: null,

            'fees_total_amount' => $feesTotal,
            'fees_currency'     => $feesCur ?: null,

            'vat_percentage' => $vat,

            'consumer_name'   => (string)($order['consumer_name'] ?? null),
            'consumer_phone'  => (string)($order['consumer_phone_number'] ?? null),
            'consumer_email'  => (string)($order['consumer_email'] ?? null),
            'consumer_comment'=> $order['consumer_comment'] ?? null,

            'pickup_eta'  => $pickupEta,
            'created_at'  => $createdAt,
            'modified_at' => $modifiedAt,

            'acceptance_status' => (string)($order['order_handling']['acceptance_status'] ?? null),

            'raw_json' => json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),

            'updated_at_local' => date('Y-m-d H:i:s'),
        ];

        $db->transaction(function() use ($db, $row, $orderId, $order) {
            // 2) upsert (MariaDB/MySQL)
            $sql = "
                INSERT INTO {{%wolt_orders}} (
                  `wolt_order_id`,`order_number`,
                  `venue_id`,`venue_name`,
                  `order_status`,`delivery_type`,`delivery_status`,
                  `basket_total_amount`,`basket_currency`,
                  `fees_total_amount`,`fees_currency`,
                  `vat_percentage`,
                  `consumer_name`,`consumer_phone`,`consumer_email`,`consumer_comment`,
                  `pickup_eta`,`created_at`,`modified_at`,
                  `acceptance_status`,
                  `raw_json`,
                  `updated_at_local`
                ) VALUES (
                  :wolt_order_id,:order_number,
                  :venue_id,:venue_name,
                  :order_status,:delivery_type,:delivery_status,
                  :basket_total_amount,:basket_currency,
                  :fees_total_amount,:fees_currency,
                  :vat_percentage,
                  :consumer_name,:consumer_phone,:consumer_email,:consumer_comment,
                  :pickup_eta,:created_at,:modified_at,
                  :acceptance_status,
                  :raw_json,
                  :updated_at_local
                )
                ON DUPLICATE KEY UPDATE
                  `order_number`=VALUES(`order_number`),
                  `venue_id`=VALUES(`venue_id`),
                  `venue_name`=VALUES(`venue_name`),
                  `order_status`=VALUES(`order_status`),
                  `delivery_type`=VALUES(`delivery_type`),
                  `delivery_status`=VALUES(`delivery_status`),
                  `basket_total_amount`=VALUES(`basket_total_amount`),
                  `basket_currency`=VALUES(`basket_currency`),
                  `fees_total_amount`=VALUES(`fees_total_amount`),
                  `fees_currency`=VALUES(`fees_currency`),
                  `vat_percentage`=VALUES(`vat_percentage`),
                  `consumer_name`=VALUES(`consumer_name`),
                  `consumer_phone`=VALUES(`consumer_phone`),
                  `consumer_email`=VALUES(`consumer_email`),
                  `consumer_comment`=VALUES(`consumer_comment`),
                  `pickup_eta`=VALUES(`pickup_eta`),
                  `created_at`=VALUES(`created_at`),
                  `modified_at`=VALUES(`modified_at`),
                  `acceptance_status`=VALUES(`acceptance_status`),
                  `raw_json`=VALUES(`raw_json`),
                  `updated_at_local`=VALUES(`updated_at_local`)
            ";
            $db->createCommand($sql, array_combine(
                array_map(fn($k)=>":$k", array_keys($row)),
                array_values($row)
            ))->execute();

            // 3) позиции: проще всего пересохранить полностью
            $db->createCommand()->delete('{{%wolt_order_items}}', ['wolt_order_id' => $orderId])->execute();

            foreach (($order['items'] ?? []) as $it) {
                $ins = [
                    'wolt_order_id' => $orderId,
                    'wolt_item_id'  => (string)($it['id'] ?? null),
                    'name'          => (string)($it['name'] ?? null),
                    'sku'           => (string)($it['sku'] ?? null),
                    'count'         => (int)($it['count'] ?? 0),

                    'unit_price_amount' => (int)($it['item_price']['unit_price']['amount'] ?? 0),
                    'total_amount'      => (int)($it['item_price']['total']['amount'] ?? 0),
                    'currency'          => (string)($it['item_price']['total']['currency'] ?? 'KZT'),

                    'vat_percentage' => isset($it['item_price']['vat_percentage'])
                        ? (float)$it['item_price']['vat_percentage']
                        : null,

                    'category_id'   => (string)($it['category']['id'] ?? null),
                    'category_name' => (string)($it['category']['name'] ?? null),

                    'raw_json' => json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];

                $db->createCommand()->insert('{{%wolt_order_items}}', $ins)->execute();
            }
        });

        return true;
    }

    private function isoToDb(string $iso): ?string
    {
        $iso = trim($iso);
        if ($iso === '') return null;

        // 2026-01-14T10:54:38.756Z -> 2026-01-14 10:54:38 (UTC)
        $iso = preg_replace('/\.\d+Z$/', 'Z', $iso);
        $ts = strtotime($iso);
        if ($ts === false) return null;

        return gmdate('Y-m-d H:i:s', $ts);
    }

    private function firstItemVat(array $order): ?float
    {
        $items = $order['items'] ?? [];
        if (!is_array($items) || empty($items)) return null;
        $vat = $items[0]['item_price']['vat_percentage'] ?? null;
        return $vat !== null ? (float)$vat : null;
    }
}
