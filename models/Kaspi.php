<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\OrdersDemands;

class Kaspi extends Model
{

  private static function getKaspiToken($shopid)
  {
    switch($shopid){
      case 'accio':
        return 'dbU852Hq+JDbq5OiGDE+lZOkbpKgNX/qFfYfQTBYU60=';
        break;
      case 'ItalFood':
        return 'GBdEjOo4M4miI1ghi/yN/y9L6BZMrpE3UKRx4Vsc0lM=';
        break;
      case 'kasta':
        return 'BiQdZihpwlTXKY2Ny6mCQiVnPHw8YwuuXExf6o1PB+8=';
        break;
    }
  }

  public function getKaspiShops()
  {
    return [
      'accio',
      'ItalFood',
      'kasta'
    ];
  }

  public function getKaspiOrders($shopid, $kaspiState = false, $kaspiStatus = false, $modifyTime = '-15 minutes')
  {
    $token = self::getKaspiToken($shopid);

    $curl = curl_init();

    $queryDate = new \DateTime(date('Y-m-d 00:00:00'));
    $queryDate = $queryDate->modify($modifyTime);

    $filter                                       = [];
    $filter['page[number]']                       = 0;
    $filter['page[size]']                         = 100;
    $filter['filter[orders][creationDate][$ge]']  = $queryDate->format('Uv');
    if($kaspiState):
      $filter['filter[orders][state]'] = $kaspiState;
    endif;
    if($kaspiStatus):
      $filter['filter[orders][status]'] = $kaspiStatus;
    endif;

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://kaspi.kz/shop/api/v2/orders?' . http_build_query($filter),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'X-Auth-Token: ' . $token
      ),
    ));
    $response = curl_exec($curl);
    return json_decode($response);
  }

  public function getKaspiOrderProducts($oid,$shopid)
  {
    $token = self::getKaspiToken($shopid);

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://kaspi.kz/shop/api/v2/orders/' . $oid . '/entries',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'X-Auth-Token: ' . $token
      ),
    ));

    $response = curl_exec($curl);
    return json_decode($response);
  }

  public function getKaspiLinkData($link,$shopid)
  {
    $token = self::getKaspiToken($shopid);

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $link,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'X-Auth-Token: ' . $token
      ),
    ));

    $response = curl_exec($curl);
    return json_decode($response);
  }

  public function getKaspiDeliveryDate($order)
  {
    if($order->attributes->isKaspiDelivery == 1){
      $deliveryDate = $order->attributes->plannedDeliveryDate;
      $seconds = $deliveryDate / 1000;
      $deliveryDate = date("Y-m-d", strtotime('@' . $seconds));
    }
    else {
      $deliveryDate = false;
    }

    return $deliveryDate;
  }

  public function setKaspiOrderStatus($order,$orderStatus,$shopid)
  {
    $token = self::getKaspiToken($shopid);

    $data                           = (object)array();
    $data->data                     = (object)array();
    $data->data->type               = 'orders';
    $data->data->id                 = $order->kaspiOrderExtId;
    $data->data->attributes         = (object)array();
    $data->data->attributes->status = $orderStatus;

    switch($orderStatus){
      case 'ACCEPTED_BY_MERCHANT':
        $data->data->attributes->code   = $order->kaspiOrderId;
        break;
      case 'ASSEMBLE':
        $data->data->attributes->numberOfSpace = $order->numOfPlaces;
        break;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"https://kaspi.kz/shop/api/v2/orders");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);

    $headers = [
      'Content-Type: application/vnd.api+json',
      'X-Auth-Token: ' . $token
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $server_output = json_decode(curl_exec ($ch));

    curl_close ($ch);

  }

  public function getPointsTitles($shopid)
  {
    $points = (object)array();
    switch($shopid){
      case 'accio':
        $points->pp1name = 'Accio_PP1';
        $points->pp2name = 'Accio_PP2';
        $points->pp15name = 'Accio_PP15';
        break;
      case 'ItalFood':
        $points->pp1name = '30093069_PP1';
        $points->pp2name = '30093069_PP2';
        $points->pp15name = '30093069_PP15';
        break;
      case 'kasta':
        $points->pp1name = '30224658_PP1';
        $points->pp2name = '30224658_PP2';
        $points->pp15name = false;
        break;
    }

    return $points;
  }
}
 
