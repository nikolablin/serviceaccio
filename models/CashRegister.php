<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\CashRegisterShifts;

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

        file_put_contents(__DIR__ . '/../logs/ms_service/ukassa_receipt.txt',
            print_r($res,true) . "\n----\n",
            FILE_APPEND
        );

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

    public static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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

    public static function getSectionIdByRegister(string $cashRegisterCode): int
    {
        $cfg = Yii::$app->params['ukassa']['sections'] ?? [];
        if (empty($cfg[$cashRegisterCode])) {
            throw new \RuntimeException("cashbox_id not configured for cash_register={$cashRegisterCode}");
        }
        return (int)$cfg[$cashRegisterCode];
    }

    /* =========================================================
     * DRAFT (DB)
     * ========================================================= */

     public static function uuidV5(string $namespaceUuid, string $name): string
     {
         // namespaceUuid должен быть валидным UUID
         $ns = str_replace(['-','{','}'], '', $namespaceUuid);
         if (strlen($ns) !== 32) {
             throw new \InvalidArgumentException('Invalid namespace UUID');
         }

         $nsBytes = hex2bin($ns);
         $hash = sha1($nsBytes . $name, true); // 20 bytes

         $bytes = substr($hash, 0, 16);

         // version 5
         $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
         // variant RFC 4122
         $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

         $hex = bin2hex($bytes);
         return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
     }

     public static function stableUkassaUuid(string $demandId, string $cashRegister, string $receiptType): string
     {
         // фиксированный namespace (можешь свой постоянный uuid)
         $namespace = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

         $name = $demandId . '|' . $cashRegister . '|' . $receiptType;

         return self::uuidV5($namespace, $name);
     }

     public static function sendReceiptByIdGuarded(int $receiptId, bool $dryRun = true): array
     {
         $receipt = OrdersReceipts::findOne($receiptId);
         if (!$receipt) throw new \RuntimeException("Receipt not found: {$receiptId}");

         // уже напечатан/успешен — не шлём
         if (!empty($receipt->ukassa_ticket_number) || $receipt->ukassa_status === 'sent') {
             return [
                 'ok' => true,
                 'skipped' => true,
                 'reason' => 'already_sent',
                 'receipt_id' => $receiptId,
             ];
         }

         // atomic-guard: только один поток может поставить sending
         $affected = OrdersReceipts::updateAll(
             ['ukassa_status' => 'sending', 'updated_at' => date('Y-m-d H:i:s')],
             [
                 'and',
                 ['id' => $receiptId],
                 ['not in', 'ukassa_status', ['sending', 'sent']],
             ]
         );

         if ($affected === 0) {
             return [
                 'ok' => true,
                 'skipped' => true,
                 'reason' => 'already_sending_or_sent',
                 'receipt_id' => $receiptId,
             ];
         }

         // дальше вызываем твой sendReceiptById (но он сейчас сам пишет status sent/error)
         return self::sendReceiptById($receiptId, $dryRun);
     }

     public static function upsertReceiptDraft(string $cashRegisterCode, array $meta, array $payload): int
     {
         $meta['cash_register'] = $cashRegisterCode;

         $now = date('Y-m-d H:i:s');
         $newJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

         $existingId = OrdersReceipts::find()
             ->select(['id'])
             ->where([
                 'moysklad_demand_id' => (string)($meta['moysklad_demand_id'] ?? ''),
                 'cash_register'      => $cashRegisterCode,
                 'receipt_type'       => (string)($meta['receipt_type'] ?? 'sale'),
             ])
             ->orderBy(['id' => SORT_DESC])
             ->scalar();

         if ($existingId) {
             /** @var OrdersReceipts $row */
             $row = OrdersReceipts::findOne((int)$existingId);
             if (!$row) return (int)$existingId;

             // ✅ если уже успешный — выходим
             if (!empty($row->ukassa_ticket_number) || $row->ukassa_status === 'sent') {
                 return (int)$row->id;
             }

             $oldJson = (string)$row->request_json;
             $alreadyTried = !empty($row->response_json) || in_array($row->ukassa_status, ['sending','error'], true);

             // ⚠️ если уже пытались отправлять и payload изменился — создаём НОВУЮ запись (и новый v4 ключ)
             if ($alreadyTried && $oldJson !== '' && $oldJson !== $newJson) {
                 // создаём новую
             } else {
                 // ✅ можно обновить существующую запись БЕЗ смены idempotency_key
                 $row->request_json  = $newJson;
                 $row->response_json = null;
                 $row->error_text    = null;
                 $row->ukassa_status = 'prepared';
                 $row->updated_at    = $now;
                 $row->save(false);
                 return (int)$row->id;
             }
         }

         // --- CREATE NEW RECORD ---
         $row = new OrdersReceipts();
         $row->order_id           = isset($meta['order_id']) ? (int)$meta['order_id'] : null;
         $row->moysklad_order_id  = (string)($meta['moysklad_order_id'] ?? null);
         $row->moysklad_demand_id = (string)($meta['moysklad_demand_id'] ?? null);
         $row->cash_register      = $cashRegisterCode;
         $row->receipt_type       = (string)($meta['receipt_type'] ?? 'sale');

         // ✅ ТОЛЬКО v4
         $row->idempotency_key    = !empty($meta['idempotency_key'])
             ? (string)$meta['idempotency_key']
             : self::uuidV4();

         $row->ukassa_receipt_id    = null;
         $row->ukassa_ticket_number = null;
         $row->ukassa_status        = 'prepared';
         $row->total_amount         = 0;

         $row->request_json  = $newJson;
         $row->response_json = null;
         $row->error_text    = null;

         $row->printed_at = null;
         $row->created_at = $now;
         $row->updated_at = $now;

         $row->save(false);
         return (int)$row->id;
     }


     // public static function upsertReceiptDraft(string $cashRegisterCode, array $meta, array $payload): int
     // {
     //     $meta['cash_register'] = $cashRegisterCode;
     //
     //     // idempotency_key должен быть UUID и СТАБИЛЬНЫЙ для этой операции
     //     // (иначе UKassa будет создавать новый чек при повторе)
     //     if (empty($meta['idempotency_key'])) {
     //         $meta['idempotency_key'] = self::stableUkassaUuid(
     //             (string)($meta['moysklad_demand_id'] ?? ''),
     //             $cashRegisterCode,
     //             (string)($meta['receipt_type'] ?? 'sale')
     //         );
     //     }
     //
     //     $now = date('Y-m-d H:i:s');
     //
     //     // 1) пробуем найти существующий
     //     $existingId = OrdersReceipts::find()
     //         ->select(['id'])
     //         ->where([
     //             'moysklad_demand_id' => (string)($meta['moysklad_demand_id'] ?? ''),
     //             'cash_register'      => $cashRegisterCode,
     //             'receipt_type'       => (string)($meta['receipt_type'] ?? 'sale'),
     //         ])
     //         ->orderBy(['id' => SORT_DESC])
     //         ->scalar();
     //
     //     if ($existingId) {
     //         $row = OrdersReceipts::findOne((int)$existingId);
     //
     //         // если уже напечатан — payload не трогаем (иначе будет путаница)
     //         if (!empty($row->ukassa_ticket_number) || $row->ukassa_status === 'sent') {
     //             return (int)$row->id;
     //         }
     //
     //         $newJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
     //         $oldJson = (string)$row->request_json;
     //
     //         $alreadyTried = !empty($row->response_json) || in_array($row->ukassa_status, ['sending','error'], true);
     //
     //         if ($alreadyTried && $oldJson !== '' && $oldJson !== $newJson) {
     //             // создаём новую запись, новый ключ
     //             $meta['idempotency_key'] = self::uuidV4(); // или uuidV5(namespace, demand|cash|type|sha1($newJson))
     //             unset($existingId); // принудительно пойдём в ветку создания ниже
     //         } else {
     //             // можно безопасно обновить (ещё не отправляли, или payload тот же)
     //             $row->request_json  = $newJson;
     //             $row->response_json = null;
     //             $row->error_text    = null;
     //             $row->ukassa_status = 'prepared';
     //             $row->updated_at    = $now;
     //             $row->save(false);
     //             return (int)$row->id;
     //         }
     //
     //         $row->request_json  = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
     //         $row->response_json = null;
     //         $row->error_text    = null;
     //         $row->ukassa_status = 'prepared';
     //         $row->updated_at    = $now;
     //         $row->save(false);
     //
     //         return (int)$row->id;
     //     }
     //
     //     // 2) иначе создаём (и ловим уникальность при гонке)
     //     $row = new OrdersReceipts();
     //     $row->order_id           = isset($meta['order_id']) ? (int)$meta['order_id'] : null;
     //     $row->moysklad_order_id  = (string)($meta['moysklad_order_id'] ?? null);
     //     $row->moysklad_demand_id = (string)($meta['moysklad_demand_id'] ?? null);
     //     $row->cash_register      = $cashRegisterCode;
     //     $row->receipt_type       = (string)($meta['receipt_type'] ?? 'sale');
     //     $row->idempotency_key    = (string)$meta['idempotency_key'];
     //
     //     $row->ukassa_receipt_id    = null;
     //     $row->ukassa_ticket_number = null;
     //     $row->ukassa_status        = 'prepared';
     //     $row->total_amount         = 0;
     //
     //     $row->request_json  = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
     //     $row->response_json = null;
     //     $row->error_text    = null;
     //
     //     $row->printed_at = null;
     //     $row->created_at = $now;
     //     $row->updated_at = $now;
     //
     //     try {
     //         $row->save(false);
     //         return (int)$row->id;
     //     } catch (\Throwable $e) {
     //         // гонка: запись уже создали
     //         $id = OrdersReceipts::find()
     //             ->select(['id'])
     //             ->where([
     //                 'moysklad_demand_id' => (string)($meta['moysklad_demand_id'] ?? ''),
     //                 'cash_register'      => $cashRegisterCode,
     //                 'receipt_type'       => (string)($meta['receipt_type'] ?? 'sale'),
     //             ])
     //             ->orderBy(['id' => SORT_DESC])
     //             ->scalar();
     //
     //         if ($id) return (int)$id;
     //
     //         throw $e;
     //     }
     // }

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
            'Authorization: Token ' . $token,
            'IDEMPOTENCY-KEY: ' . $receipt->idempotency_key,
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

        $receipt->response_json         = $res['raw'] ?: null;
        $receipt->ukassa_receipt_id     = $res['ok'] ? $res['json']['data']['id'] : null;
        $receipt->ukassa_ticket_number  = $res['ok'] ? $res['json']['data']['fixed_check'] : null;
        $receipt->total_amount          = $res['ok'] ? $res['json']['data']['total_amount'] : 0;
        $receipt->ukassa_status         = $res['ok'] ? 'sent' : 'error';
        $receipt->error_text            = $res['ok'] ? null : ("http={$res['code']} err={$res['err']}");
        $receipt->printed_at            = $res['ok'] ? date('Y-m-d H:i:s') : null;
        $receipt->updated_at            = date('Y-m-d H:i:s');
        $receipt->save(false);

        $res['receipt_id'] = $receiptId;
        $res['url']        = $url;
        $res['headers']    = $headers;
        $res['payload']    = $payload;
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

    private static function ukassaGetJson(
        string $url,
        array $headers = [],
        bool $withResponseHeaders = false
    ): array {
        // ✅ гарантируем обязательные заголовки
        $baseHeaders = [
            'Accept: application/json',
        ];

        // чистим входные заголовки от дублей Accept / Content-Type
        $headers = array_values(array_filter($headers, function ($h) {
            $h = strtolower(trim((string)$h));
            return $h !== ''
                && !str_starts_with($h, 'accept:')
                && !str_starts_with($h, 'content-type:');
        }));

        $finalHeaders = array_merge($baseHeaders, $headers);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => $finalHeaders,
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
        $json = (is_string($rawBody) && $rawBody !== '')
            ? json_decode($rawBody, true)
            : null;

        return [
            'ok'           => $ok,
            'code'         => $code,
            'err'          => $err,
            'raw'          => $rawBody,
            'json'         => $json,
            'url'          => $url,
            'headers_raw'  => $rawHeaders,
        ];
    }

    /* =========================================================
     * ADDONS
     * ========================================================= */

    public static function getDepartmentData($cashboxNum)
    {
      $cashboxId = self::getCashboxIdByRegister($cashboxNum);
      $token     = self::getUkassaTokenByCashRegister($cashboxNum);
      $url       = self::ukassaUrl('departmentPath', '/api/department/');

      $res = self::ukassaGetJson(
        $url,
        [
            'Authorization: Token ' . $token,
            'Content-Type: application/json'
        ],
        true // если нужны response headers
      );

      if (!$res['ok']) {
        throw new \RuntimeException(
            "UKassa GET failed http={$res['code']} err={$res['err']} body={$res['raw']}"
        );
      }
    }

    public static function closeZShiftAndSave(string $cashRegisterCode): array
    {
        $cashboxId = self::getCashboxIdByRegister($cashRegisterCode);
        if (!$cashboxId) {
            throw new \RuntimeException("Cashbox not found: {$cashRegisterCode}");
        }

        $token = self::getUkassaTokenByCashRegister($cashRegisterCode);

        $url = self::ukassaUrl('kassaPath', '/api/kassa/close_z_shift/');

        $payload = [
            'kassa'     => (int)$cashboxId,
            'html_code' => false,
        ];

        $headers = [
            'Authorization: Token ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $now = date('Y-m-d H:i:s');

        $res = self::ukassaPostJson($url, $payload, $headers, false);

        // ⬇️ сохраняем ВСЕГДА
        $row = new CashRegisterShifts();
        $row->cash_register = $cashRegisterCode;
        $row->kassa_id      = (int)$cashboxId;
        $row->requested_at  = $now;
        $row->created_at    = $now;
        $row->updated_at    = $now;

        $row->request_json  = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $row->response_json = $res['raw'] ?? null;

        if (!empty($res['ok'])) {
            $data = $res['json']['data'] ?? [];

            $row->z_shift_number = $data['z_shift'] ?? null;
            $row->closed_at      = $data['closed_at'] ?? $now;
            $row->status         = 'closed';
        } else {
            $row->status     = 'error';
            $row->error_text = "http={$res['code']} err={$res['err']}";
        }

        $row->save(false);

        return $res;
    }

}
