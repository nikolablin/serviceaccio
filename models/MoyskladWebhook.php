<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\Moysklad;

class MoyskladWebhook extends Model
{

  // Methods examples
  // MoyskladWebhook::createWebhook('https://service.accio.kz/webhook/deletedemand','DELETE','demand');
  // MoyskladWebhook::updateWebhook('cc844360-d6c6-11f0-0a80-17a00000c79c','https://service.accio.kz/webhook/updatedemand');

  public function getWebhooksList()
  {
      return  (object)[
                'create' => [
                              'customerorder' => '3986a554-d6c6-11f0-0a80-1b430000bf7e',
                              'demand' => '7ac59787-d6c6-11f0-0a80-17a00000b49e',
                              'salesreturn' => '412cd11b-e59c-11f0-0a80-039900be1903'
                            ],
                'update' => [
                              'customerorder' => 'e76e2d62-d6c6-11f0-0a80-02bf0000d1df',
                              'demand' => 'cc844360-d6c6-11f0-0a80-17a00000c79c',
                              'salesreturn' => '6f3bca41-e59c-11f0-0a80-192f00bd18c1'
                            ],
                'delete' => [
                              'customerorder' => '77cd000d-d6c7-11f0-0a80-10390000e919',
                              'demand' => '8ca6201b-d6c7-11f0-0a80-08bd0000eb06'
                            ]
              ];
  }

  public static function createWebhook($actionUrl,$action,$entityType)
  {
    $moysklad = new Moysklad();
    $access = $moysklad->getMSLoginPassword();

    $url = "https://api.moysklad.ru/api/remap/1.2/entity/webhook";

    $data = [
        "url"        => $actionUrl,
        "action"     => $action,
        "entityType" => $entityType
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Basic " . base64_encode($access->login . ':' . $access->password),
            "Accept-Encoding: gzip",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS     => json_encode($data),
    ]);

    $response = curl_exec($ch);
    // $response = gzdecode($response);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);

    curl_close($ch);

    echo "HTTP code: " . $httpcode . "\n";
    print('<pre>');
    print_r($response);
    print('</pre>');
    echo "Error: " . $error . "\n";
  }

  public static function updateWebhook($webhookId, $newUrl)
  {
      $moysklad = new Moysklad();
      $access = $moysklad->getMSLoginPassword();

      $endpoint = "https://api.moysklad.ru/api/remap/1.2/entity/webhook/" . $webhookId;

      $data = [
          "url"    => $newUrl,
          "diffType" => 'FIELDS'
      ];

      $ch = curl_init($endpoint);

      curl_setopt_array($ch, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CUSTOMREQUEST  => "PUT",
          CURLOPT_HTTPHEADER     => [
              "Authorization: Basic " . base64_encode($access->login . ':' . $access->password),
              "Content-Type: application/json"
              // Accept-Encoding не указываем вручную
          ],
          CURLOPT_ENCODING       => "",
          CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
      ]);

      $response = curl_exec($ch);
      $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error    = curl_error($ch);

      curl_close($ch);

      echo "HTTP code: " . $httpcode . "\n";
      echo "<pre>";
      print_r($response);
      echo "</pre>";
      echo "Error: " . $error . "\n";

      // Если нужно — вернуть JSON:
      // return json_decode($response, true);
  }

}
