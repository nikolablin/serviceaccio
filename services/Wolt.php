<?php

namespace app\services;

use Yii;

class Wolt
{
    private string $baseUrl;
    private string $accessToken;
    private int $timeout;

    public function __construct()
    {
        $cfg = Yii::$app->params['wolt'] ?? [];

        $this->baseUrl     = $cfg['test_baseUrl'];
        $this->accessToken = $cfg['test_order_api_key'];
        $this->timeout     = $cfg['timeout'];

        if ($this->accessToken === '') {
            throw new \RuntimeException('Wolt accessToken not configured');
        }
    }


    public function getOrder(string $orderId): array
    {
        return $this->request('GET', "/v2/orders/{$orderId}");
    }

    public function acceptOrder(string $orderId): array
    {
        $path = '/orders/' . rawurlencode($orderId) . '/accept';

        return $this->request('PUT', $path, null);
    }

    public function confirmPreOrder(string $orderId): array
    {
        $path = '/orders/' . rawurlencode($orderId) . '/confirm-preorder';

        return $this->request('PUT', $path, null);
    }

    public function markOrderReady(string $orderId): array
    {
        $path = '/orders/' . rawurlencode($orderId) . '/ready';

        return $this->request('PUT', $path, null);
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $path = '/' . ltrim(trim($path), '/');
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);

        $headers = [
            'WOLT-API-KEY: ' . $this->accessToken,
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

        $decoded = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
          file_put_contents(__DIR__ . '/../logs/wolt/errors.txt',print_r($decoded,true) . PHP_EOL, FILE_APPEND);
            Yii::warning([
                'event' => 'wolt_http_error',
                'url'   => $url,
                'http'  => $code,
                'body'  => $decoded ?? $body,
            ], 'wolt');

            throw new \RuntimeException("Wolt HTTP {$code}");
        }

        if (!is_array($decoded)) {
            return [
                'ok'   => true,
                'http' => $code,
                'body' => $body
            ];
        }

        return $decoded;
    }

}
