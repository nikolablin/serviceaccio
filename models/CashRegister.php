<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\OrdersDemands;

class CashRegister extends Model
{

  public static function getCashRegisterList()
  {
    return  [
      'UK00003842',
      'UK00003857',
      'UK00003854',
      'UK00006240',
      'UK00006241'
    ];
  }

  public static function getCashRegisterApiKeys()
  {
    return (object)array(
      'UK00003842' => (object)array(
        'login' => 'ip.pastukhov90@ya.ru',
        'pwd' => '901128301025Dt+'
      ),
      'UK00003857' => (object)array(
        'login' => 'fin@acciostore.kz',
        'pwd' => 'Vm00855102@@$$'
      ),
      'UK00003854' => (object)array(
        'login' => '2336623@gmail.com',
        'pwd' => 'AccioToo2023@@$$'
      ),
      'UK00006240' => (object)array(
        'login' => 'mazurviktoriia@gmail.com',
        'pwd' => 'Ital2026@@$$'
      ),
      'UK00006241' => (object)array(
        'login' => 'acciokazakhstan@gmail.com',
        'pwd' => 'Scelta2026@@$$'
      ),
    );
  }

  public static function loginUkassaUser($mail, $password, $hashline)
  {
      $url = 'https://ukassa.kz/api/auth/login/';

      $payload = json_encode([
          'email'    => $mail,
          'password' => $password,
          'hashline' => $hashline,
      ], JSON_UNESCAPED_UNICODE);

      $ch = curl_init($url);

      curl_setopt_array($ch, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_POST           => true,
          CURLOPT_POSTFIELDS     => $payload,
          CURLOPT_HTTPHEADER     => [
              'Content-Type: application/json',
              'Accept: application/json',
              'Content-Length: ' . strlen($payload),
          ],
          CURLOPT_TIMEOUT        => 30,
      ]);

      $response = curl_exec($ch);

      if ($response === false) {
          $error = curl_error($ch);
          curl_close($ch);
          throw new \RuntimeException('UKassa login error: ' . $error);
      }

      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($httpCode !== 200) {
          throw new \RuntimeException(
              'UKassa login HTTP ' . $httpCode . ': ' . $response
          );
      }

      return json_decode($response, true);
  }


  public static function getCashRegisterDataList()
  {
    $companyUuid = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

    $url = 'https://stage.ukassa.kz/api/v1/cashbox/?' . http_build_query([
        'company_uuid'    => $companyUuid,
        'is_activated'    => 'true',
        'skip_pagination' => 'true',
    ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $companyUuid,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new \RuntimeException('CashRegister API error: ' . $error);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new \RuntimeException(
            'CashRegister API HTTP ' . $httpCode . ': ' . $response
        );
    }

    return json_decode($response, true);
  }

}
