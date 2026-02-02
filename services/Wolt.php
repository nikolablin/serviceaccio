<?php

namespace app\services;

use Yii;

class Wolt
{
    private string $baseUrl;
    private string $accessTokenAlmaty;
    private string $accessTokenAstana;
    private int $timeout;

    public function __construct()
    {
        $cfg = Yii::$app->params['wolt'] ?? [];

        $this->baseUrl            = $cfg['baseUrl'];
        $this->accessTokenAlmaty  = $cfg['almaty_order_api_key'];
        $this->accessTokenAstana  = $cfg['astana_order_api_key'];
        $this->timeout            = $cfg['timeout'];
    }


    public function getOrder(string $orderId, ?string $venueId = null): array
    {
        return $this->request('GET', "/v2/orders/{$orderId}", null, $venueId);
    }

    public function acceptOrder(string $orderId, ?string $venueId = null): array
    {
        $path = '/orders/' . rawurlencode($orderId) . '/accept';
        return $this->request('PUT', $path, null, $venueId);
    }

    public function confirmPreOrder(string $orderId, ?string $venueId = null): array
    {
        $path = '/orders/' . rawurlencode($orderId) . '/confirm-preorder';
        return $this->request('PUT', $path, null, $venueId);
    }

    public function markOrderReady(string $orderId, ?string $venueId = null): array
   {
       $path = '/orders/' . rawurlencode($orderId) . '/ready';
       return $this->request('PUT', $path, null, $venueId);
   }

   /**
     * Универсальный авто-выбор venue_id:
     * - если venueId передали -> используем его
     * - если нет -> делаем GET заказа, достаем venue_id и повторяем действие
     */
    public function acceptOrderAuto(string $orderId, bool $isPreorder = false): array
    {
        $order = $this->getOrder($orderId, null);
        $venueId = (string)($order['venue']['id'] ?? $order['venue_id'] ?? '');
        if ($venueId === '') {
            throw new \RuntimeException('Wolt: cannot resolve venue_id for accept');
        }

        return $isPreorder
            ? $this->confirmPreOrder($orderId, $venueId)
            : $this->acceptOrder($orderId, $venueId);
    }

    public function markOrderReadyAuto(string $orderId): array
    {
        $order = $this->getOrder($orderId, null);
        $venueId = (string)($order['venue']['id'] ?? $order['venue_id'] ?? '');
        if ($venueId === '') {
            throw new \RuntimeException('Wolt: cannot resolve venue_id for ready');
        }

        return $this->markOrderReady($orderId, $venueId);
    }

    private function resolveToken(?string $venueId): string
    {
        $venueId = (string)$venueId;

        // если venueId не дали — по умолчанию Алматы (или можешь сделать throw)
        if ($venueId === '') {
            return $this->accessTokenAlmaty;
        }

        $cfg = Yii::$app->params['wolt'] ?? [];
        $astanaVenue = (string)($cfg['astana_venue_id'] ?? '');

        if ($astanaVenue !== '' && $venueId === $astanaVenue) {
            return $this->accessTokenAstana;
        }

        return $this->accessTokenAlmaty;
    }

    private function request(string $method, string $path, ?array $payload = null, ?string $venueId = null): array
    {
        $path = '/' . ltrim(trim($path), '/');
        $url  = $this->baseUrl . $path;

        $token = $this->resolveToken($venueId);
        if ($token === '') {
            throw new \RuntimeException('Wolt token is empty (check params[wolt])');
        }

        $ch = curl_init($url);

        $headers = [
            'WOLT-API-KEY: ' . $token,
            'Accept: application/json',
        ];

        if ($payload !== null) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);


        if ($body === false) {
            Yii::error(['event' => 'wolt_curl_error', 'url' => $url, 'err' => $err], 'wolt');
            throw new \RuntimeException('Wolt curl error: ' . $err);
        }

        // 202/204 часто без JSON
        if ($code === 202 || $code === 204) {
            return ['ok' => true, 'http' => $code, 'body' => $body];
        }

        $decoded = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            Yii::warning([
                'event' => 'wolt_http_error',
                'url'   => $url,
                'http'  => $code,
                'body'  => $decoded ?? $body,
            ], 'wolt');

            throw new \RuntimeException("Wolt HTTP {$code}");
        }

        if (!is_array($decoded)) {
            return ['ok' => true, 'http' => $code, 'body' => $body];
        }

        return $decoded;
    }
}
