<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\CashRegisterShifts;
use app\models\V2Receipts;
use app\services\support\Log;

class CashRegisterV2 extends Model
{

    /* =========================================================
       * AUTH + STATIC MAPS
    * ========================================================= */

    public static function ukassaToken(string $cashRegisterCode): string
    {
       $cfg = Yii::$app->params['ukassa'] ?? [];
       $accounts = $cfg['accounts'] ?? null;

       if (!$accounts || empty($accounts[$cashRegisterCode])) {
           Log::cashboxError( "UKassa account not found for cash_register={$cashRegisterCode}" );
           return '';
       }

       $acc   = $accounts[$cashRegisterCode];
       $login = is_array($acc) ? ($acc['login'] ?? null) : ($acc->login ?? null);
       $pwd   = is_array($acc) ? ($acc['pwd'] ?? null)   : ($acc->pwd ?? null);

       if (!$login || !$pwd) {
           Log::cashboxError( "UKassa credentials incomplete for cash_register={$cashRegisterCode}" );
           return '';
       }

       $ttl = (int)($cfg['tokenCacheTtl'] ?? 0);
       $cacheKey = 'ukassa_token_' . $cashRegisterCode;

       if ($ttl > 0 && isset(Yii::$app->cache)) {
           $cached = Yii::$app->cache->get($cacheKey);
           if (is_string($cached) && $cached !== '') {
               return $cached;
           }
       }

       $url = self::url('loginPath', '/api/auth/login/');
       $payload = [
           'email'    => $login,
           'password' => $pwd,
           'hashline' => $cashRegisterCode,
       ];

       $res = self::httpPostJson($url, $payload, [], true);
       if (!$res['ok'] || !is_array($res['json'])) {
         Log::cashboxError( 'UKassa login failed http=' . $res['code'] . ' body=' . ($res['raw'] ?? '') );
         return '';
       }

       $token =
           $res['json']['auth_token']
           ?? $res['json']['token']
           ?? $res['json']['access_token']
           ?? ($res['json']['data']['auth_token'] ?? null)
           ?? ($res['json']['data']['token'] ?? null)
           ?? ($res['json']['data']['access_token'] ?? null)
           ?? null;

       if (!is_string($token) || $token === '') {
           Log::cashboxError( "UKassa token missing for cash_register={$cashRegisterCode}" );
           return '';
       }

       if ($ttl > 0 && isset(Yii::$app->cache)) {
           Yii::$app->cache->set($cacheKey, $token, $ttl);
       }

       return $token;
    }

    public static function cashboxId(string $cashRegisterCode): int
    {
       $cfg = Yii::$app->params['ukassa']['cashboxes'] ?? [];
       if (empty($cfg[$cashRegisterCode])) {
           Log::cashboxError( "cashbox_id not configured for cash_register={$cashRegisterCode}" );
           return 0;
       }
       return (int)$cfg[$cashRegisterCode];
    }

    public static function sectionId(string $cashRegisterCode): int
    {
       $cfg = Yii::$app->params['ukassa']['sections'] ?? [];
       if (empty($cfg[$cashRegisterCode])) {
           Log::cashboxError( "section_id not configured for cash_register={$cashRegisterCode}" );
           return 0;
       }
       return (int)$cfg[$cashRegisterCode];
    }


    /* =========================================================
    * DRAFT (DB)  acs43_v2_receipts
    * ========================================================= */

    /**
    * Создать/обновить черновик чека (idempotent по demand_ms_id).
    * ВНИМАНИЕ: тут не плодим новые строки — у нас UNIQUE(demand_ms_id).
    */

    public static function upsertDraft(array $meta, array $payload): int
    {
        $demandMsId = (string)($meta['demand_ms_id'] ?? '');
        if ($demandMsId === '') {
            Log::cashboxError( 'upsertDraft: demand_ms_id required' );
            return 0;
        }

        $cashRegister = (string)($meta['cash_register'] ?? '');
        if ($cashRegister === '') {
          Log::cashboxError( 'upsertDraft: cash_register required' );
          return 0;
        }

        $operation = (string)($meta['operation'] ?? 'sell');

        $now = date('Y-m-d H:i:s');

        $row = V2Receipts::find()->where(['demand_ms_id' => $demandMsId, 'operation' => $operation])->one();
        if (!$row) {
            $row = new V2Receipts();
            $row->demand_ms_id = $demandMsId;
            $row->status = 'prepared';
            $row->attempts = 0;
        }

        if (in_array($row->status, ['sent','sending'], true)) {
          return (int)$row->id;
        }

        // мета
        $row->order_ms_id   = (string)($meta['order_ms_id'] ?? $row->order_ms_id);
        $row->config_id     = isset($meta['config_id']) ? (int)$meta['config_id'] : $row->config_id;
        $row->cash_register = $cashRegister;

        $row->cashbox_id    = isset($meta['cashbox_id']) ? (int)$meta['cashbox_id'] : $row->cashbox_id;
        $row->section_id    = isset($meta['section_id']) ? (int)$meta['section_id'] : $row->section_id;

        $row->operation     = (string)($meta['operation'] ?? ($row->operation ?: 'sell'));
        $row->payment_type  = isset($meta['payment_type']) ? (int)$meta['payment_type'] : $row->payment_type;

        $total = (int)($meta['total_amount'] ?? 0);
        if ($total > 0) $row->total_amount = $total;

        // payload
        $newPayloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($newPayloadJson === false) {
            Log::cashboxError('upsertDraft: json_encode failed: ' . json_last_error_msg());
            return 0;
        }

        $oldPayloadJson = (string)($row->payload_json ?? '');
        $payloadChanged = ($oldPayloadJson !== '' && $oldPayloadJson !== $newPayloadJson);

        $row->payload_json = $newPayloadJson;

        if ($payloadChanged && !in_array($row->status, ['sent','sending'], true)) {
            // ключ должен соответствовать конкретному payload
            $row->idempotency_key = self::uuidV4();
            $row->attempts = 0;
            $row->status = 'prepared';
            $row->error_message = null;
            $row->response_json = null;
            $row->external_id = null; // если поле есть в таблице
        }

        // если до этого был error — при новом draft возвращаем в prepared
        if (in_array($row->status, ['error', 'draft'], true)) {
            $row->status = 'prepared';
            $row->error_message = null;
            $row->response_json = null;
        }

        if (empty($row->idempotency_key)) {
          $row->idempotency_key = self::uuidV4();
        }

        // timestamps (если у тебя beforeSave — можно убрать эти строки)
        if (empty($row->created_at)) $row->created_at = $now;
        $row->updated_at = $now;

        $row->save(false);
        return (int)$row->id;
    }

    /**
    * Guard от параллельной отправки: только один поток может поставить sending.
    */

    public static function sendByIdGuarded(int $id, bool $dryRun = true): array
    {
      if ($dryRun) {
        return self::sendById($id, true);
      }

      $now = date('Y-m-d H:i:s');

      $affected = V2Receipts::updateAll(
         ['status' => 'sending', 'updated_at' => $now],
         [
             'and',
             ['id' => $id],
             ['not in', 'status', ['sending', 'sent']],
         ]
      );

      if ($affected === 0) {
         return ['ok' => true, 'skipped' => true, 'reason' => 'already_sending_or_sent', 'id' => $id];
      }

      return self::sendById($id, false);
    }

    public static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
    * Отправка чека в UKassa. dryRun=true — не шлём, возвращаем сборку.
    */
    public static function sendById(int $id, bool $dryRun = true): array
    {
       /** @var V2Receipts $row */
       $row = V2Receipts::findOne($id);
       if (!$row){
         Log::cashboxError( "Receipt not found: {$id}" );
         return ['ok'=>false,'error'=>'Receipt not found','id'=>$id];
       }

       if (empty($row->cash_register)){
         Log::cashboxError( "Receipt {$id}: cash_register empty" );
         return ['ok'=>false,'error'=>'cash_register empty','id'=>$id];
       }
       if (empty($row->payload_json)){
         Log::cashboxError( "Receipt {$id}: payload_json empty" );
         return ['ok'=>false,'error'=>'payload_json empty','id'=>$id];
       }

       $payload = json_decode((string)$row->payload_json, true);
       if (!is_array($payload)){
         Log::cashboxError( "Receipt {$id}: payload_json invalid JSON" );
         return ['ok'=>false,'error'=>'payload_json invalid JSON','id'=>$id];
       }

       $row->attempts = (int)$row->attempts + 1;
       $row->updated_at = date('Y-m-d H:i:s');
       $row->save(false);

       $token = self::ukassaToken((string)$row->cash_register);
       if ($token === '') {
          $row->status = 'error';
          $row->error_message = 'token_not_received';
          $row->updated_at = date('Y-m-d H:i:s');
          $row->save(false);

          Log::cashboxError( "Receipt {$id}: token error" );
          return ['ok'=>false,'error'=>'token error','id'=>$id, 'token' => $token];
       }
       $url   = self::url('receiptPath', '/api/v1/ofd/receipt/');

       if (empty($row->idempotency_key)) {
         Log::cashboxError( "Receipt {$id}: idempotency_key empty" );
         return ['ok'=>false,'error'=>'idempotency_key empty','id'=>$id];
       }

       $headers = [
           'Authorization: Token ' . $token,
           'IDEMPOTENCY-KEY: ' . (string)$row->idempotency_key,
           'Accept: application/json',
           'Content-Type: application/json',
       ];

       if ($dryRun) {
           $row->status = 'prepared';
           $row->save(false);

           return [
               'ok'      => true,
               'dryRun'  => true,
               'id'      => $id,
               'url'     => $url,
               'headers' => $headers,
               'payload' => $payload,
           ];
       }

       $res = self::httpPostJson($url, $payload, $headers, false);

       $row->response_json = $res['raw'] ?: null;
       $row->updated_at = date('Y-m-d H:i:s');

       if (!empty($res['ok']) && is_array($res['json'])) {
           $data = $res['json']['data'] ?? [];

           $row->external_id = isset($data['id']) ? (string)$data['id'] : $row->external_id;
           // если у тебя fixed_check = номер чека
           // можно в external_id хранить id, а ticket_number вынести в отдельное поле,
           // но в новой таблице его нет — оставляем в response_json.
           $row->total_amount = isset($data['total_amount']) ? (int)$data['total_amount'] : (int)$row->total_amount;

           $row->status  = 'sent';
           $row->sent_at = date('Y-m-d H:i:s');
           $row->error_message = null;
       } else {
           $row->status = 'error';
           $row->error_message = "http={$res['code']} err=" . ($res['err'] ?? '');
       }

       $row->save(false);

       $res['id'] = $id;
       $res['url'] = $url;
       return $res;
    }

    /* =========================================================
    * HTTP
    * ========================================================= */

    private static function url(string $pathKey, string $defaultPath): string
    {
       $cfg  = Yii::$app->params['ukassa'] ?? [];
       $base = rtrim((string)($cfg['baseUrl'] ?? 'https://ukassa.kz'), '/');

       $path = (string)($cfg[$pathKey] ?? $defaultPath);
       $path = '/' . ltrim($path, '/');

       return $base . $path;
    }

    private static function httpPostJson(string $url, array $payload, array $headers = [], bool $withResponseHeaders = false): array
    {
       $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
       if ($body === false) {
           Log::cashboxError( 'httpPostJson: json_encode failed: ' . json_last_error_msg() );
           $msg = 'httpPostJson: json_encode failed: ' . json_last_error_msg();
           return [
                   'ok' => false,
                   'code' => 0,
                   'err' => $msg,
                   'raw' => null,
                   'json' => null,
                   'headers_raw' => null,
               ];
       }

       // чистим входные заголовки от дублей accept/content-type
       $headers = array_values(array_filter($headers, static function ($h) {
           $h = strtolower(trim((string)$h));
           return $h !== '' && !str_starts_with($h, 'content-type:') && !str_starts_with($h, 'accept:');
       }));

       $finalHeaders = array_merge([
           'Content-Type: application/json',
           'Accept: application/json',
       ], $headers);

       $ch = curl_init($url);
       curl_setopt_array($ch, [
           CURLOPT_RETURNTRANSFER => true,
           CURLOPT_CUSTOMREQUEST  => 'POST',
           CURLOPT_POSTFIELDS     => $body,
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
       $json = (is_string($rawBody) && $rawBody !== '') ? json_decode($rawBody, true) : null;

       return [
           'ok'   => $ok,
           'code' => $code,
           'err'  => $err,
           'raw'  => $rawBody,
           'json' => $json,
           'headers_raw' => $rawHeaders,
       ];
    }

    private static function httpGetJson( string $url, array $headers = [], bool $withResponseHeaders = false): array
    {
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


    /* Other */

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

    public static function getDepartmentData($cashboxNum)
    {
      $cashboxId = self::cashboxId($cashboxNum);
      $token     = self::ukassaToken($cashboxNum);
      $url       = self::url('departmentPath', '/api/department/');

      $res = self::httpGetJson(
        $url,
        [
            'Authorization: Token ' . $token,
            'Content-Type: application/json'
        ],
        true // если нужны response headers
      );

      if (!$res['ok']) {
        Log::cashboxError( "UKassa GET failed http={$res['code']} err={$res['err']} body={$res['raw']}" );
        return [];
      }
    }

    public static function closeZShiftAndSave(string $cashRegisterCode): array
    {
        $cashboxId = self::cashboxId($cashRegisterCode);
        if (!$cashboxId) {
          Log::cashboxError( "Cashbox not found: {$cashRegisterCode}" );
          return ['ok' => false];
        }

        $token = self::ukassaToken($cashRegisterCode);

        $url = self::url('kassaPath', '/api/kassa/close_z_shift/');

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

        $res = self::httpPostJson($url, $payload, $headers, false);

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
