<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\OrdersDemands;
use app\models\KaspiOrders;
use app\models\Telegram;
use app\models\Moysklad;

class Halyk extends Model
{

  public function getHalykToken()
  {
    $url = 'https://halykmarket.kz/gw/auth/token';

    $data = [
        'grant_type' => 'client_credentials',
        'client_id' => Yii::$app->params['halyk']['client_id'],
        'client_secret' => Yii::$app->params['halyk']['client_secret'],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
  }

  public function getHalykOrderProducts($token,$oid)
  {
    $data = [
        'orderIds' => [$oid],
    ];

    $ch = curl_init('https://halykmarket.kz/gw/merchant/public/merchant/product/details-by-order');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json;charset=UTF-8',
        'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
        'Authorization: Bearer ' . $token,
        'Connection: keep-alive',
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
  }

  public function getHalykOrders($token,$status,$dateFrom,$dateTo)
  {
    $params = http_build_query([
                          'size' => 100,
                          'page' => 1,
                          'status' => $status,
                      ]);
    $url = 'https://halykmarket.kz/gw/merchant/public/order/v1?' . $params;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
  }

}
