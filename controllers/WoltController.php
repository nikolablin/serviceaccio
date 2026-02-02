<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use app\models\OrdersConfigTable;
use app\models\Moysklad;
use app\models\Website;
use app\models\Telegram;

class WoltController extends Controller
{
    public $enableCsrfValidation = false;

    public function beforeAction($action)
    {
        $this->layout = false;
        Yii::$app->response->format = Response::FORMAT_JSON;

        // только POST
        // if (!Yii::$app->request->isPost) {
        //     throw new BadRequestHttpException('POST required');
        // }

        // проверка token из GET
        $this->checkToken();

        return parent::beforeAction($action);
    }

    public function actionGet()
    {
        $errorLog = __DIR__ . '/../logs/wolt/errors.txt';
        $dataLog  = __DIR__ . '/../logs/wolt/data.txt';

        $raw = Yii::$app->request->rawBody;

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new BadRequestHttpException('Invalid JSON');
        }

        // Логируем кратко (без персональных данных по максимуму)
        $logData = [
            'datetime'  => date('Y-m-d H:i:s'),
            'ip'        => Yii::$app->request->userIP,
            'headers'   => [
                'Headers' => Yii::$app->request->headers->toArray(),
                'Content-Type' => Yii::$app->request->headers->get('Content-Type'),
            ],
            'url' => Yii::$app->request->absoluteUrl,
            'payload' => $data
        ];

        file_put_contents(
            $dataLog,
            json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL . str_repeat('-', 80) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        $type     = (string)($data['type'] ?? '');
        $status   = (string)($data['order']['status'] ?? '');
        $orderId  = (string)($data['order']['id'] ?? '');
        $venueId  = (string)($data['order']['venue_id'] ?? '');

        file_put_contents(
            $dataLog,
            date('Y-m-d H:i:s') . " -- EVENT type={$type} status={$status} orderId={$orderId} venueId={$venueId}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($type === 'order.notification' && $orderId !== ''){

          $wolt         = new \app\services\Wolt();
          $woltimporter = new \app\services\WoltOrderImporter();
          $website      = new Website();
          $moysklad     = new Moysklad();
          $telegram     = new Telegram();

          switch($status){
            // Новый заказ
            case 'CREATED':
              $toCreate = false;

              try {
                  $order = $wolt->getOrder($orderId,$venueId);

                  if($order){
                    $toCreate = true;
                  }
                  file_put_contents(
                      __DIR__ . '/../logs/wolt/order_' . $orderId . '.json',
                      json_encode($order, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                  );
              }
              catch (\Throwable $e) {
                  file_put_contents(
                      __DIR__ . '/../logs/wolt/errors.txt',
                      date('Y-m-d H:i') . ' -- ' . $orderId . print_r($e->getMessage(),true) . PHP_EOL . PHP_EOL,
                      FILE_APPEND
                  );

              }

              if($toCreate){
                $moyskladOrders     = $moysklad->checkOrderInMoySkladByMarketplaceCode($order['order_number'],'externalcode');

                if(!$moyskladOrders){
                  $projectConfig    = OrdersConfigTable::findOne(['project' => Yii::$app->params['moysklad']['woltProject']]);
                  $moySkladRemains  = $moysklad->getProductsRemains(); // 023870f6-ee91-11ea-0a80-05f20007444d - almaty, 805d5404-3797-11eb-0a80-01b1001ba27a - astana, 1e1187c1-85e6-11ed-0a80-0dbe006f385b - БЦ Success
                  $moySkladRemains  = json_decode($moySkladRemains);
                  $moySkladCities   = $moysklad->getMoySkladCities();
                  $addOrderToMoySklad = true;

                  $creatingOrder = (object)array();
                  $creatingOrder->comment       = $order['consumer_comment'];
                  $creatingOrder->products      = [];
                  $creatingOrder->project       = $projectConfig->project;
                  $creatingOrder->organization  = $projectConfig->organization;
                  $creatingOrder->orderStatus   = $projectConfig->status;

                  switch($order['venue']['id']){
                    case Yii::$app->params['wolt']['astana_venue_id']:
                      $creatingOrder->warehouse = '805d5404-3797-11eb-0a80-01b1001ba27a';
                      $creatingOrder->city      = '4a9f5042-3470-11eb-0a80-056100175606';
                      break;
                    default:
                      $creatingOrder->warehouse = '023870f6-ee91-11ea-0a80-05f20007444d';
                      $creatingOrder->city      = '4a9f3624-3470-11eb-0a80-056100175604';
                  }

                  foreach ($order['items'] as $item) {
                    $productInfo            = $item;
                    $productWebSiteData     = $website->getProductWebDataBySku($productInfo['sku']);

                    if($productWebSiteData){
                      $productRemainsInStock  = $moysklad->productRemainsCheckByArray($productInfo['sku'],$productInfo['count'],$moySkladRemains,$productWebSiteData->product_id);

                      foreach ($productRemainsInStock as $key => $remain) {
                        if($key == $creatingOrder->warehouse){
                          if($remain < (int)$productInfo['count']){
                            $creatingOrder->orderStatus = Yii::$app->params['moysklad']['takeToJobOrderState']; // Если беда с количеством, то создаем со статусом - Взять в работу !!! Нужно учесть этот момент при вебхуке создания заказа
                          }
                        }
                      }

                      if($order['type'] == 'preorder'){
                        $creatingOrder->orderStatus = Yii::$app->params['moysklad']['takeToJobOrderState'];
                      }

                      if($productWebSiteData->product_type == 'bundle'){
                        $creatingOrder->comment = 'В заказе есть комплект!';
                      }

                      $prObj            = (object)array();
                      $prObj->title     = $productInfo['name'];
                      $prObj->sku       = $productInfo['sku'];
                      $prObj->pid       = $productWebSiteData->product_id;
                      $prObj->quantity  = (int)$productInfo['count'];
                      $prObj->price     = ($productInfo['item_price']['unit_price']['amount']) / 100;
                      $prObj->type      = $productWebSiteData->product_type;
                      $prObj->vat       = false;
                      $creatingOrder->products[] = $prObj;
                    }
                    else {
                      $addOrderToMoySklad = false;
                      $telegram->sendTelegramMessage('Ошибка создания заказа Wolt #' . $order['order_number'] . '. Не найден товар SKU - ' . $productInfo['sku'] . '.', 'wolt');
                    }
                  }

                  // CREATE CONTRAGENT
                  $userName         = trim($order['consumer_name']);
                  $userPhone        = $order['consumer_phone_number'];

                  if($userPhone){
                    if ($userPhone[0] != '8' && $userPhone[0] != '+') {
                      $userPhone = '+7' . $userPhone;
                    }
                    $msContragent     = $moysklad->searchContragentByPhone($userPhone);
                    if(!$msContragent){ $msContragent = $moysklad->createContragent($userName, $userPhone, ''); }
                  }
                  else {
                    $msContragent = $moysklad->createContragent($userName, '+70000000000', '');
                  }

                  $creatingOrder->contragent    = $msContragent->id;

                  $creatingOrder->orderId         = $order['order_number'];
                  $creatingOrder->orderExtId      = $order['id'];
                  if($order['delivery']['time']){
                    $dateTime = new \DateTime($order['delivery']['time'], new \DateTimeZone('UTC'));
                    $dateTime->setTimezone(new \DateTimeZone('Asia/Almaty'));
                    $creatingOrder->deliveryDate    = $dateTime->format('Y-m-d');
                    $creatingOrder->deliveryTime    = $moysklad->getDeliveryTime($dateTime,'wolt');
                  }
                  else {
                    $creatingOrder->deliveryDate    = false;
                    $creatingOrder->deliveryTime    = false;
                  }

                  if (!empty($order['cash_payment'])) {
                    $creatingOrder->paymentType     = Yii::$app->params['moysklad']['cashPaymentTypeId'];
                    $creatingOrder->paymentStatus   = Yii::$app->params['moysklad']['cashPaymentStatus'];
                  }
                  else {
                    $creatingOrder->paymentType   = $projectConfig->payment_type;
                    $creatingOrder->paymentStatus   = $projectConfig->payment_status;
                  }

                  $creatingOrder->fiscalBill      = $projectConfig->fiscal;

                  $creatingOrder->cityStr         = '';
                  $creatingOrder->address         = '';
                  $creatingOrder->deliveryCost    = (string)0;

                  switch($order['delivery']['type']){
                    case 'takeaway':
                      $creatingOrder->deliveryType    = 'c45aea40-54cd-11ec-0a80-095800022a93';
                      break;
                    default:
                      $creatingOrder->deliveryType  = $projectConfig->delivery_service;
                      if(!empty($order['delivery']['location'])){
                        $creatingOrder->cityStr = (string)($order['delivery']['location']['city'] ?? '');
                        $creatingOrder->address = (string)($order['delivery']['location']['street_address'] ?? '');
                      }
                  }

                  $creatingOrder->autoorder = true;

                  if($addOrderToMoySklad){
                    try {
                        $msOrder = $moysklad->createOrder($creatingOrder, 'wolt', $order['order_number']);

                        $imported = $woltimporter->upsertOrder($order);

                        if (!empty($msOrder) && (isset($msOrder->id) || isset($msOrder->meta))) {

                            try {

                              $venueId = (string)($order['venue']['id'] ?? $order['venue_id'] ?? '');
                              $isPre   = (($order['type'] ?? '') == 'preorder');

                              $woltAccept = $isPre
                                  ? $wolt->confirmPreOrder($order['id'], $venueId)
                                  : $wolt->acceptOrder($order['id'], $venueId);

                                file_put_contents(
                                    $dataLog,
                                    date('Y-m-d H:i:s') . " -- ACCEPT OK order={$order['id']} num={$order['order_number']} resp=" . substr(print_r($woltAccept, true), 0, 1000) . PHP_EOL,
                                    FILE_APPEND | LOCK_EX
                                );

                            } catch (\Throwable $e) {

                                file_put_contents(
                                    $errorLog,
                                    date('Y-m-d H:i:s') . " -- ACCEPT FAIL order={$order['id']} num={$order['order_number']} err=" . $e->getMessage() . PHP_EOL,
                                    FILE_APPEND | LOCK_EX
                                );
                            }
                        }
                    } catch (\Throwable $e) {
                        file_put_contents(
                            $errorLog,
                            date('Y-m-d H:i:s') . " -- CREATE ORDER FAILED | " . $e->getMessage() . PHP_EOL . PHP_EOL,
                            FILE_APPEND | LOCK_EX
                        );
                    }
                  }


                }

              }
              break;
            // Отмена заказа
            case 'CANCELED':
              $toCancel = false;
              try {
                  $order = $wolt->getOrder($orderId,$venueId);

                  if($order){
                    $toCancel = true;
                  }
                  file_put_contents(
                      __DIR__ . '/../logs/wolt/order_canceled' . $orderId . '.json',
                      json_encode($order, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                  );

              }
              catch (\Throwable $e) {
                  file_put_contents(
                      __DIR__ . '/../logs/wolt/errors.txt',
                      date('Y-m-d H:i') . ' -- ' . $orderId . print_r($e->getMessage(),true) . PHP_EOL . PHP_EOL,
                      FILE_APPEND
                  );
              }

              if($toCancel){

                $moyskladOrders = $moysklad->checkOrderInMoySkladByMarketplaceCode($order['order_number'],'externalcode');

                if($moyskladOrders && !empty($moyskladOrders)){

                  // Проверяем, есть ли отгрузки
                  if($moyskladOrders[0]->demands && !empty($moyskladOrders[0]->demands)){

                    $demand = $moyskladOrders[0]->demands[0];
                    $demand = $moysklad->getHrefData($demand->meta->href . '?expand=state');

                    if($demand){

                      // Статус
                      $demandStateId              = $demand->state->id;

                      // Состояние оплаты
                      $paymentStatusAttrId        = Yii::$app->params['moysklad']['demandPaymentStatusAttrId'] ?? null;
                      $paymentStatusId            = $paymentStatusAttrId ? $moysklad->getAttributeValueId($demand, $paymentStatusAttrId) : null;
                      $isPayed                    = ($paymentStatusId === (Yii::$app->params['moysklad']['cashPaymentStatusPayed'] ?? ''));

                      if($isPayed){
                        if($demandStateId == Yii::$app->params['moysklad']['demandUpdateHandler']['stateDemandCollected']){
                          // Статус Собран и чек выбит - меняем на Провести возврат
                          $demandStateMeta = $moysklad->buildStateMeta('demand', Yii::$app->params['moysklad']['demandUpdateHandler']['stateDemandDoReturn']);
                          $moysklad->updateDemandState($demand->id,$demandStateMeta);
                        }
                        else {
                          // Другой статус - Чека скорее всего нет. Меняем на БЕЗ ЧЕКА - Возврат на склад
                          $demandStateMeta = $moysklad->buildStateMeta('demand', Yii::$app->params['moysklad']['demandUpdateHandler']['stateDemandReturnNoCheck']);
                          $moysklad->updateDemandState($demand->id,$demandStateMeta);
                        }
                      }
                      else {
                        // Статус оплаты - Не оплачен. Меняем на БЕЗ ЧЕКА - Возврат на склад
                        $demandStateMeta = $moysklad->buildStateMeta('demand', Yii::$app->params['moysklad']['demandUpdateHandler']['stateDemandReturnNoCheck']);
                        $moysklad->updateDemandState($demand->id,$demandStateMeta);
                      }
                    }
                  }
                }
              }
              break;
          }

        }

        return [
            'ok' => true,
        ];
    }

    private function checkToken(): void
    {
        $expected = Yii::$app->params['wolt']['token'] ?? '';

        if ($expected === '') {
            Yii::error('Wolt token not configured', 'wolt');
            throw new ForbiddenHttpException('Webhook not configured');
        }

        $token = Yii::$app->request->get('token', '');

        if (!hash_equals($expected, $token)) {
            Yii::warning([
                'event' => 'wolt_bad_token',
                'ip'    => Yii::$app->request->userIP,
                'token' => $token,
            ], 'wolt');

            throw new ForbiddenHttpException('Invalid token');
        }
    }
}
