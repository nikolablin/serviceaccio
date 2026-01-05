<?php
namespace app\models;

use Yii;
use yii\base\Model;

class CashRegister extends Model
{
    public static function getCashRegisterList(): array
    {
        return [
            'UK00003842',
            'UK00003857',
            'UK00003854',
            'UK00006240',
            'UK00006241',
        ];
    }

    /* =========================================================
     * UKASSA AUTH
     * ========================================================= */

    public static function getUkassaTokenByCashRegister(string $cashRegisterCode): string
    {
        $cfg = Yii::$app->params['ukassa'] ?? [];
        $hashline = $cashRegisterCode;

        $accounts = $cfg['accounts'] ?? null;
        if (!$accounts) {
            // fallback если вдруг не вынесено в params
            $accounts = (array)self::getCashRegisterMailList();
        }

        if (empty($accounts[$cashRegisterCode])) {
            throw new \RuntimeException("UKassa account not found for cash_register={$cashRegisterCode}");
        }

        $acc   = $accounts[$cashRegisterCode];

        $login = is_array($acc) ? ($acc['login'] ?? null) : ($acc->login ?? null);
        $pwd   = is_array($acc) ? ($acc['pwd'] ?? null)   : ($acc->pwd ?? null);

        if (!$login || !$pwd) {
            throw new \RuntimeException("UKassa credentials incomplete for cash_register={$cashRegisterCode}");
        }

        // ✅ КЭШ ТОКЕНА (по кассе)
        $ttl = (int)($cfg['tokenCacheTtl'] ?? 0);
        $cacheKey = 'ukassa_token_' . $cashRegisterCode;

        if ($ttl > 0 && isset(Yii::$app->cache)) {
            $cached = Yii::$app->cache->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $res = self::loginUkassaUser($login, $pwd, $hashline);

        $token = self::extractToken($res);
        if (!$token) {
            throw new \RuntimeException(
                "UKassa login ok but token missing for cash_register={$cashRegisterCode}: " .
                json_encode($res, JSON_UNESCAPED_UNICODE)
            );
        }

        // ✅ сохраняем токен
        if ($ttl > 0 && isset(Yii::$app->cache)) {
            Yii::$app->cache->set($cacheKey, $token, $ttl);
        }

        return $token;
    }

    private static function extractToken(array $res): ?string
    {
        $token =
            $res['auth_token']
            ?? $res['token']
            ?? $res['access_token']
            ?? ($res['data']['auth_token'] ?? null)
            ?? ($res['data']['token'] ?? null)
            ?? ($res['data']['access_token'] ?? null)
            ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function loginUkassaUser(string $mail, string $password, string $hashline): array
    {
        $cfg  = Yii::$app->params['ukassa'] ?? [];
        $url  = self::ukassaUrl('loginPath', '/api/auth/login/');

        $payload = [
            'email'    => $mail,
            'password' => $password,
            'hashline' => $hashline,
        ];

        $res = self::ukassaPostJson($url, $payload, [], true);

        // login должен вернуть JSON
        if (!$res['ok']) {
            throw new \RuntimeException('UKassa login HTTP ' . $res['code'] . ': ' . ($res['raw'] ?? ''));
        }
        if (!is_array($res['json'])) {
            throw new \RuntimeException('UKassa login invalid JSON: ' . ($res['raw'] ?? ''));
        }

        return $res['json'];
    }

    public static function getCashboxIdByRegister(string $cashRegisterCode): int
    {
        $cfg = Yii::$app->params['ukassa']['cashboxes'] ?? [];
        if (empty($cfg[$cashRegisterCode])) {
            throw new \RuntimeException("cashbox_id not configured for cash_register={$cashRegisterCode}");
        }
        return (int)$cfg[$cashRegisterCode];
    }

    /* =========================================================
     * RECEIPT PAYLOAD
     * ========================================================= */

    public static function buildReceiptPayload(string $cashRegisterCode, array $data): array
    {
        $cashboxId = self::getCashboxIdByRegister($cashRegisterCode);
        if ($cashboxId <= 0) {
            throw new \RuntimeException('cashbox_id is required');
        }

        $items = (array)($data['items'] ?? []);
        if (!$items) throw new \RuntimeException('items is required');

        $payments = (array)($data['payments'] ?? []);
        if (!$payments) throw new \RuntimeException('payments is required');

        $total = 0;
        $taxTotal = 0.0;

        foreach ($items as $i => $it) {
            $qty  = (int)($it['quantity'] ?? 0);
            $unit = (int)($it['unit_price'] ?? 0);
            $line = (int)($it['total_amount'] ?? ($qty * $unit));

            $items[$i]['total_amount'] = $line;
            $total += $line;

            if (!isset($items[$i]['tax_amount'])) {
                $rate = (float)($it['tax_rate'] ?? 0);
                $tax  = $rate > 0 ? ($line - ($line / (1 + $rate / 100))) : 0;
                $items[$i]['tax_amount'] = round($tax, 2);
            }
            $taxTotal += (float)$items[$i]['tax_amount'];

            // дефолты
            $items[$i] += [
                'is_storno' => false,
                'catalog_id' => 0,
                'section_code' => (string)($it['section_code'] ?? '0'),
                'excise_stamp' => '',
                'mark_code' => '',
                'physical_label' => '',
                'product_id' => '',
                'barcode' => $it['barcode'] ?? '',
                'ntin' => (string)($it['ntin'] ?? ''),
                'list_excise_stamp' => (array)($it['list_excise_stamp'] ?? []),
                'measure_unit_code' => (string)($it['measure_unit_code'] ?? '796'),
                'discount' => ['is_storno' => false, 'sum_' => 0],
            ];
        }

        $taken = 0;
        foreach ($payments as $p) $taken += (int)($p['sum_'] ?? 0);

        if ($taken <= 0) {
            $taken = $total;
            $payments = [['type' => 0, 'sum_' => $total]];
        }

        $payload = [
            'operation_type' => (int)($data['operation_type'] ?? 2),
            'cashbox_id'     => $cashboxId,
            'items'          => array_values($items),
            'payments'       => array_values($payments),
            'tax_amount'     => round($taxTotal, 2),
            'amounts'        => [
                'total'    => $total,
                'taken'    => $taken,
                'change'   => max(0, $taken - $total),
                'markup'   => 0,
                'discount' => 0,
            ],
            'is_return_html' => (bool)($data['is_return_html'] ?? false),
        ];

        $iin = trim((string)($data['customer_iin_bin'] ?? ''));
        if ($iin !== '') {
            $payload['customer_info'] = ['iin_or_bin' => $iin];
        }

        return $payload;
    }

    /* =========================================================
     * DRAFT (DB)
     * ========================================================= */

    public static function createReceiptDraft(string $cashRegisterCode, array $meta, array $data): int
    {
        $payload = self::buildReceiptPayload($cashRegisterCode, $data);

        $meta['cash_register'] = $cashRegisterCode;
        if (empty($meta['idempotency_key'])) {
            $meta['idempotency_key'] = 'r_' . bin2hex(random_bytes(16));
        }

        return self::saveReceiptDraft($meta, $payload);
    }

    public static function saveReceiptDraft(array $meta, array $payload): int
    {
        $idempotencyKey = (string)($meta['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            throw new \RuntimeException('idempotency_key is required');
        }

        // быстрее, чем ->one()
        if (OrdersReceipts::find()->where(['idempotency_key' => $idempotencyKey])->exists()) {
            $row = OrdersReceipts::find()->select(['id'])->where(['idempotency_key' => $idempotencyKey])->one();
            return (int)$row->id;
        }

        $now = date('Y-m-d H:i:s');
        $totalAmount = (float)($payload['amounts']['total'] ?? 0);
        if ($totalAmount <= 0) throw new \RuntimeException('Receipt total_amount must be > 0');

        $row = new OrdersReceipts();

        $row->order_id           = isset($meta['order_id']) ? (int)$meta['order_id'] : null;
        $row->moysklad_order_id  = (string)($meta['moysklad_order_id'] ?? null);
        $row->moysklad_demand_id = (string)($meta['moysklad_demand_id'] ?? null);

        $row->cash_register   = (string)($meta['cash_register'] ?? '');
        $row->receipt_type    = (string)($meta['receipt_type'] ?? 'sale'); // sale|return
        $row->idempotency_key = $idempotencyKey;

        $row->total_amount = $totalAmount;
        $row->currency     = 'KZT';

        $row->ukassa_receipt_id    = null;
        $row->ukassa_ticket_number = null;
        $row->ukassa_status        = null;

        $row->request_json  = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $row->response_json = null;
        $row->error_text    = null;

        $row->printed_at = null;
        $row->created_at = $now;
        $row->updated_at = $now;

        $row->save(false);

        return (int)$row->id;
    }

    /* =========================================================
     * SEND (DRY RUN / REAL)
     * ========================================================= */

    public static function sendReceiptById(int $receiptId, bool $dryRun = true): array
    {
        $receipt = OrdersReceipts::findOne($receiptId);
        if (!$receipt) throw new \RuntimeException("Receipt not found: {$receiptId}");

        if (empty($receipt->cash_register)) throw new \RuntimeException("Receipt {$receiptId}: cash_register is empty");
        if (empty($receipt->idempotency_key)) throw new \RuntimeException("Receipt {$receiptId}: idempotency_key is empty");
        if (empty($receipt->request_json)) throw new \RuntimeException("Receipt {$receiptId}: request_json is empty");

        $payload = json_decode($receipt->request_json, true);
        if (!is_array($payload)) throw new \RuntimeException("Receipt {$receiptId}: request_json invalid JSON");

        $url = self::ukassaUrl('receiptPath', '/api/v1/ofd/receipt/');
        $token = self::getUkassaTokenByCashRegister($receipt->cash_register);

        $headers = [
            'Authorization: ' . $token,
            'Idempotency-Key: ' . $receipt->idempotency_key,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        // 🔴 dry-run: только читаем payload
        if ($dryRun) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

            $receipt->ukassa_status = 'prepared';
            $receipt->updated_at = date('Y-m-d H:i:s');
            $receipt->save(false);

            return [
                'ok' => true,
                'dryRun' => true,
                'receipt_id' => $receiptId,
                'url' => $url,
                'headers' => $headers,
                'payload' => $payload,
            ];
        }

        // ✅ real send
        $res = self::ukassaPostJson($url, $payload, $headers, false);

        $receipt->response_json = $res['raw'] ?: null;
        $receipt->ukassa_status = $res['ok'] ? 'sent' : 'error';
        $receipt->error_text    = $res['ok'] ? null : ("http={$res['code']} err={$res['err']}");
        $receipt->printed_at    = $res['ok'] ? date('Y-m-d H:i:s') : null;
        $receipt->updated_at    = date('Y-m-d H:i:s');
        $receipt->save(false);

        $res['receipt_id'] = $receiptId;
        return $res;
    }

    /* =========================================================
     * HTTP HELPERS
     * ========================================================= */

    private static function ukassaUrl(string $pathKey, string $defaultPath): string
    {
        $cfg  = Yii::$app->params['ukassa'] ?? [];
        $base = rtrim((string)($cfg['baseUrl'] ?? 'https://ukassa.kz'), '/');

        $path = (string)($cfg[$pathKey] ?? $defaultPath);
        $path = '/' . ltrim($path, '/'); // гарантированно начинается с /

        return $base . $path;
    }

    private static function ukassaPostJson(
        string $url,
        array $payload,
        array $headers = [],
        bool $withResponseHeaders = false
    ): array {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            throw new \RuntimeException('ukassaPostJson: json_encode failed: ' . json_last_error_msg());
        }

        // ✅ гарантируем обязательные заголовки
        $baseHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        // чтобы не было дублей Content-Type/Accept, можно “почистить” входные
        $headers = array_values(array_filter($headers, function ($h) {
            $h = strtolower(trim((string)$h));
            return $h !== '' && !str_starts_with($h, 'content-type:') && !str_starts_with($h, 'accept:');
        }));

        $finalHeaders = array_merge($baseHeaders, $headers);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $finalHeaders, // ❗ не добавляем Content-Length вручную
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => $withResponseHeaders,
        ]);

        $resp = curl_exec($ch);
        $err  = $resp === false ? curl_error($ch) : null;
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $rawHeaders = null;
        $rawBody = $resp;

        if ($withResponseHeaders && is_string($resp)) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $rawHeaders = substr($resp, 0, $headerSize);
            $rawBody    = substr($resp, $headerSize);
        }

        curl_close($ch);

        $ok = ($err === null) && ($code >= 200 && $code < 300);
        $json = (is_string($rawBody) && $rawBody !== '') ? json_decode($rawBody, true) : null;

        return [
            'ok'   => $ok,
            'code' => $code,
            'err'  => $err,
            'raw'  => $rawBody,
            'json' => $json,
            'url'  => $url,
            'headers_raw' => $rawHeaders,
        ];
    }
}
