<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\OrdersDemands;
use app\models\KaspiOrders;
use app\models\Telegram;
use app\models\Moysklad;

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

  public function getKaspiOrders($shopkey, $kaspiState = false, $kaspiStatus = false, $modifyTime = '-15 minutes')
  {
    $token = Yii::$app->params['moysklad']['kaspiTokens'][$shopkey];

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

  public function getKaspiOrderProducts($oid,$shopkey)
  {
    $token = Yii::$app->params['moysklad']['kaspiTokens'][$shopkey];

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

    curl_close($curl);

    return json_decode($response);
  }

  public function getKaspiLinkData($link,$shopkey)
  {
    $token = Yii::$app->params['moysklad']['kaspiTokens'][$shopkey];

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

  public function setKaspiOrderStatus($order,$orderStatus,$shopkey)
  {
    $token = Yii::$app->params['moysklad']['kaspiTokens'][$shopkey];

    $data                           = (object)array();
    $data->data                     = (object)array();
    $data->data->type               = 'orders';
    $data->data->id                 = $order->kaspiOrderExtId;
    $data->data->attributes         = (object)array();
    $data->data->attributes->status = $orderStatus;

    file_put_contents(__DIR__ . '/../logs/kaspi/kaspiChangeStatusOrders.txt', date('d.m.Y') . PHP_EOL . $token . PHP_EOL . print_r($data,true) . PHP_EOL,FILE_APPEND);

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

    file_put_contents(__DIR__ . '/../logs/kaspi/kaspiChangeStatusOrders.txt', print_r($server_output,true) . PHP_EOL . PHP_EOL,FILE_APPEND);

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
      case 'ital':
        $points->pp1name = '30093069_PP1';
        $points->pp2name = '30093069_PP2';
        $points->pp15name = '30093069_PP15';
        break;
      case 'tutto':
        $points->pp1name = '30224658_PP1';
        $points->pp2name = '30224658_PP2';
        $points->pp15name = false;
        break;
    }

    return $points;
  }

  public static function getKaspiOrder($orderId,$shopkey,$checkwaybill = false)
  {
    $token = Yii::$app->params['moysklad']['kaspiTokens'][$shopkey];

    $curl = curl_init();
    $goreturn = false;

    do {
      sleep(3);

      curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://kaspi.kz/shop/api/v2/orders?filter[orders][code]=' . $orderId,
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

      if($checkwaybill){
        if(!empty(json_decode($response)->data[0]->attributes->kaspiDelivery->waybill)){
          $goreturn = true;
        }
      }
      else {
        $goreturn = true;
      }

    } while ( $goreturn == false );

    return json_decode($response);
  }

  public function setKaspiReadyForDelivery($kaspiOrderCode,$numberOfPlaces,$status,$projectId)
  {
    $kaspiOrders            = new KaspiOrders();
    $telegram               = new Telegram();
    $moysklad               = new Moysklad();

    $order                  = (object)array();
    $order->kaspiOrderId    = $kaspiOrderCode;
    $order->numOfPlaces     = $numberOfPlaces;

    $kaspiProjects = Yii::$app->params['moysklad']['kaspiProjects'];

    $order->shopId = false;
    foreach ($kaspiProjects as $shopkey => $shopid) {
      if($projectId == $shopid){
        $order->shopId = $shopkey;
      }
    }

    if(!$order->shopId){
      $telegram->sendTelegramMessage(
        'Kaspi readyForDelivery: неизвестный projectId=' . ($kaspiOrderCode ?? 'NULL') . ' для заказа #' . $kaspiOrderCode,
        'kaspi'
      );
      return;
    }

    switch($status) {
      case 'readyForDelivery':
        $dbOrder = KaspiOrders::findByCode($order->kaspiOrderId);

        if (!$dbOrder) {
            $telegram->sendTelegramMessage(
                'Kaspi readyForDelivery: заказ #' . $order->kaspiOrderId . ' (' . $order->shopId . ') не найден в БД.',
                'kaspi'
            );
            return;
        }

        $order->kaspiOrderExtId = $dbOrder->extOrderId;

        // 1) Идемпотентность: выполняем дальше только если статус "created"
        if (($dbOrder->status ?? null) !== 'created') {
            return;
        }

        // 2) В Kaspi меняем статус заказа
        self::setKaspiOrderStatus($order, 'ASSEMBLE', $order->shopId);
        $telegram->sendTelegramMessage(
            'Заказ Kaspi #' . $order->kaspiOrderId . ' (' . $order->shopId . ') успешно помечен к отправке.',
            'kaspi'
        );

        // 3) В БД обновляем статус через модель
        $dbOrder->updateStatus('assemble');

        // 4) Получаем waybill (из API Kaspi)
        $orderData = self::getKaspiOrder($order->kaspiOrderId, $order->shopId, true);

        $waybillLink = null;
        if (!empty($orderData->data) && !empty($orderData->data[0]->attributes->kaspiDelivery->waybill)) {
            $waybillLink = $orderData->data[0]->attributes->kaspiDelivery->waybill;
        }

        if (!$waybillLink) {
            // Накладной нет — просто выходим
            return;
        }

        // 6) Если waybill уже записан — не повторяем
        if (!empty($dbOrder->waybill)) {
            return;
        }

        // 7) Добавляем waybill к отгрузке(ам) в МС и отмечаем
        $orderInfo = $moysklad->checkOrderInMoySkladByMarketplaceCode($kaspiOrderCode);

        if (!empty($orderInfo)) {
            foreach ($orderInfo as $iorder) {
                $demandsList = $iorder->demands ?? [];

                foreach ($demandsList as $demand) {
                    $href = $demand->meta->href ?? null;
                    if (!$href) continue;


                    $demandId = basename($href);

                    $moysklad->setFileToDemand($demandId, $waybillLink);
                    $moysklad->markWaybillDelivery($demandId);
                }
            }
        }

        // 8) Сохраняем ссылку в БД через модель
        $dbOrder->saveWaybill($waybillLink);

        $telegram->sendTelegramMessage(
            'Заказ Kaspi #' . $order->kaspiOrderId . ' (' . $order->shopId . ') - накладная успешно добавлена!',
            'kaspi'
        );
        break;
    }

  }

  public function checkOrderSentToClient($order)
  {
    $db = new db();
    $db->init('localhost:3306', 'acciosto_user', 'Uj524#b2l', 'acciosto_db');

    $query = 'SELECT * FROM `vu1dh_kaspi_orders` WHERE `order_id` = "' . $order->attributes->code . '"';
    $result = $db->sql($query);

    if(!empty($result)){
      $result = array_values($result)[0];
      if((int)$result['sent_to_client']){
        return true; // Already sent to client
      }
      else {
        return false;
      }
    }
    else {
      return true; // Not exist in DB, skip sending;
    }
    return false; // Not sent
  }
}
