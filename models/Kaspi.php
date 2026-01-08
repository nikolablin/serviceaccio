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

  public static function getKaspiOrder($orderId,$shopid,$checkwaybill = false)
  {
    $token = self::getKaspiToken($shopid);

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

  public function setKaspiReadyForDelivery($kaspiOrderCode,$numberOfPlaces,$status,$organizationId)
  {
    $kaspiOrders            = new KaspiOrders();
    $telegram               = new Telegram();
    $moysklad               = new Moysklad();

    $order                  = (object)array();
    $order->kaspiOrderId    = $kaspiOrderCode;
    $order->numOfPlaces     = $numberOfPlaces;

    switch (trim($organizationId ?? '')) {
      case '5f351348-d269-11f0-0a80-15120016d622': $order->shopId = 'accio'; break;
      case '98777142-d26a-11f0-0a80-1be40016550a': $order->shopId = 'ItalFood'; break;
      case '431a8172-d26a-11f0-0a80-0f110016cabd': $order->shopId = 'kasta'; break;
      default:
          $telegram->sendTelegramMessage(
              'Kaspi readyForDelivery: неизвестный orgId=' . ($organizationId ?? 'NULL') . ' для заказа #' . $kaspiOrderCode,
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
        file_put_contents(__DIR__ . '/orderdata.txt', print_r($orderData, true) . PHP_EOL . PHP_EOL, FILE_APPEND);

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

                    $demandId = explode('/', $href);
                    $demandId = end($demandId);

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
}
