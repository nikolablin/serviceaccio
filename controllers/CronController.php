<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\models\Kaspi;
use app\models\Moysklad;
use app\models\MoyskladV2;
use app\models\Website;
use app\models\Telegram;
use app\models\Whatsapp;
use app\models\KaspiOrders;
use app\models\OrdersConfigTable;
use app\models\CashRegister;

class CronController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * Отключаем layout
     */
    public function beforeAction($action)
    {
        $this->layout = false;
        return parent::beforeAction($action);
    }

    /**
     * Проверка доступа по токену
     */
    protected function checkAccess()
    {
        $token = Yii::$app->request->get('token');

        if ($token !== Yii::$app->params['cronToken']) {
            Yii::warning('Cron access denied', 'cron');
            throw new \yii\web\ForbiddenHttpException('Access denied');
        }

        // $allowedIps = ['127.0.0.1', '1.2.3.4'];
        //
        // if (!in_array(Yii::$app->request->userIP, $allowedIps)) {
        //     throw new \yii\web\ForbiddenHttpException('IP denied');
        // }
    }

    public function actionSetkaspiorders() // Создание заказов Каспи
    {
      $moysklad     = new Moysklad();
      $moysklad2    = new MoyskladV2();
      $kaspi        = new Kaspi();
      $kaspiOrders  = new KaspiOrders();
      $website      = new Website();
      $telegram     = new Telegram();

      $kaspiShops = Yii::$app->params['moysklad']['kaspiProjects'];

      foreach ($kaspiShops as $shopkey => $shopid) {
        $projectConfig  = OrdersConfigTable::findOne(['project' => $shopid]);

        $orders = $kaspi->getKaspiOrders($shopkey,'NEW');

        file_put_contents(__DIR__ . '/../logs/kaspi/kaspiCreateOrders.txt', date('d.m.Y H:i') . PHP_EOL . $shopkey . PHP_EOL . print_r($orders,true) . PHP_EOL . PHP_EOL,FILE_APPEND);

        // $orders = file_get_contents(__DIR__ . '/kaspitest.json');
        // $orders = json_decode($orders);

        if(!empty($orders->data)){
          $moySkladRemains  = $moysklad->getProductsRemains(); // 023870f6-ee91-11ea-0a80-05f20007444d - almaty, 805d5404-3797-11eb-0a80-01b1001ba27a - astana, 1e1187c1-85e6-11ed-0a80-0dbe006f385b - БЦ Success
          $moySkladRemains  = json_decode($moySkladRemains);
          $moySkladCities   = $moysklad->getMoySkladCities();

          foreach($orders->data AS $order){
            $moyskladOrders     = $moysklad->checkOrderInMoySkladByMarketplaceCode($order->attributes->code);
            $addOrderToMoySklad = true;

            // ------ NEW ORDER ------
            if(!$moyskladOrders){
              $creatingOrder                = (object)array();

              // PRODUCTS
              $productsData                 = $kaspi->getKaspiOrderProducts($order->id,$shopkey);
              $creatingOrder->products      = [];
              $creatingOrder->orderStatus   = $projectConfig->status;
              $creatingOrder->comment       = false;
              $orderPickupPointCity         = false;

              // PP names
              $pointsNames = $kaspi->getPointsTitles($shopkey);

              switch ($order->attributes->pickupPointId){
                case $pointsNames->pp1name:
                  $orderPickupPointCity = 'almaty';
                  break;
                case $pointsNames->pp2name:
                  $orderPickupPointCity = 'astana';
                  break;
                case $pointsNames->pp15name:
                  $orderPickupPointCity = 'success';
                  break;
              }

              $addVatToProduct = $moysklad2->checkOrganizationVatEnabled($projectConfig->organization);

              foreach ($productsData->data as $prdata) {
                $productInfo            = $kaspi->getKaspiLinkData('https://kaspi.kz/shop/api/v2/masterproducts/' . $prdata->relationships->product->data->id . '/merchantProduct',$shopkey);
                $productWebSiteData     = $website->getProductWebDataBySku($productInfo->data->attributes->code);
                if($productWebSiteData){
                  $productRemainsInStock  = $moysklad->productRemainsCheckByArray($productInfo->data->attributes->code,$prdata->attributes->quantity,$moySkladRemains,$productWebSiteData->product_id);

                  foreach ($productRemainsInStock as $key => $remain) {
                    if($key == $orderPickupPointCity){
                      if($remain < $prdata->attributes->quantity){
                        $creatingOrder->orderStatus = Yii::$app->params['moysklad']['takeToJobOrderState']; // Если беда с количеством, то создаем со статусом - Взять в работу !!! Нужно учесть этот момент при вебхуке создания заказа
                      }
                    }
                  }

                  if($productWebSiteData->product_type == 'bundle'){
                    $creatingOrder->comment = 'В заказе есть комплект!';
                  }

                  $prObj            = (object)array();
                  $prObj->title     = $productInfo->data->attributes->name;
                  $prObj->sku       = $productInfo->data->attributes->code;
                  $prObj->pid       = $productWebSiteData->product_id;
                  $prObj->quantity  = $prdata->attributes->quantity;
                  $prObj->price     = $prdata->attributes->basePrice;
                  $prObj->type      = $productWebSiteData->product_type;
                  $prObj->vat       = ($addVatToProduct) ? Yii::$app->params['moyskladv2']['vat']['value'] : false;
                  $creatingOrder->products[] = $prObj;
                }
                else {
                  $addOrderToMoySklad = false;
                  $telegram->sendTelegramMessage('Ошибка создания заказа Kaspi #' . $order->attributes->code . ' (' . $shopkey . '). Не найден товар SKU - ' . $productInfo->data->attributes->code . '.', 'kaspi');
                }
              }

              // CREATE CONTRAGENT
              $kaspiUserName    = $order->attributes->customer->firstName;
              $kaspiUserSurname = $order->attributes->customer->lastName;
              $kaspiUserPhone   = $order->attributes->customer->cellPhone;
              if($kaspiUserPhone[0] != '8' OR $kaspiUserPhone[0] != '+'){
                $kaspiUserPhone = '+7' . $kaspiUserPhone;
              }
              $msContragent     = $moysklad->searchContragentByPhone($kaspiUserPhone);
              if(!$msContragent){ $msContragent = $moysklad->createContragent($kaspiUserSurname . ' ' . $kaspiUserName, $kaspiUserPhone, ''); }

              // CREATING ORDER
              $creatingOrder->stock         = $order->attributes->pickupPointId;
              $creatingOrder->organization  = $projectConfig->organization;
              $creatingOrder->project       = $projectConfig->project;
              $creatingOrder->contragent    = $msContragent->id;

              switch($orderPickupPointCity){
                case 'astana':
                  $creatingOrder->warehouse = '805d5404-3797-11eb-0a80-01b1001ba27a';
                  break;
                case 'success':
                  $creatingOrder->warehouse = '1e1187c1-85e6-11ed-0a80-0dbe006f385b';
                  break;
                default:
                  $creatingOrder->warehouse = '023870f6-ee91-11ea-0a80-05f20007444d';
              }

              $creatingOrder->orderId    = $order->attributes->code;
              $creatingOrder->orderExtId = $order->id;
              $creatingOrder->deliveryDate    = $kaspi->getKaspiDeliveryDate($order);
              $creatingOrder->deliveryTime    = ($creatingOrder->deliveryDate) ? $moysklad->getDeliveryTime($order->attributes->plannedDeliveryDate,'kaspi') : false;

              $creatingOrder->paymentStatus   = $projectConfig->payment_status;
              $creatingOrder->paymentType     = $projectConfig->payment_type;
              $creatingOrder->fiscalBill      = $projectConfig->fiscal;
              $creatingOrder->cityStr         = '';
              $creatingOrder->address         = '';
              $creatingOrder->deliveryCost = property_exists($order->attributes,'deliveryCost') ? (string)$order->attributes->deliveryCost : (string)0;

              if(property_exists($order->attributes,'deliveryAddress')):
                $creatingOrder->cityStr       = $order->attributes->deliveryAddress->town;
                $creatingOrder->city          = $moysklad->getCityId($order->attributes->deliveryAddress->town,$moySkladCities);
                $creatingOrder->address       = $order->attributes->deliveryAddress->formattedAddress;
              else:
                $creatingOrder->city          = 'ce22d9f6-4941-11ed-0a80-00bd000e47e9';
              endif;

              // DELIVERY
              switch($order->attributes->isKaspiDelivery){
                case true:
                  switch($order->attributes->deliveryMode){
                    case 'DELIVERY_LOCAL':
                      if($order->attributes->kaspiDelivery->express){
                        $creatingOrder->deliveryType = '90b239fc-8c74-11eb-0a80-03dd0005a26d'; // Kaspi Delivery
                      }
                      else {
                        $creatingOrder->deliveryType = '7f6acbef-b4ee-11ed-0a80-005b003d9e76';
                      }
                      break;
                    case 'DELIVERY_PICKUP':
                    case 'DELIVERY_REGIONAL_PICKUP':
                    case 'DELIVERY_REGIONAL_TODOOR':
                      $creatingOrder->deliveryType = '7f6acbef-b4ee-11ed-0a80-005b003d9e76'; // Zammler
                      break;
                  }
                  break;
                case false:
                  $creatingOrder->deliveryType = 'c45aea40-54cd-11ec-0a80-095800022a93'; // Самовывоз
                  break;
              }

              if($addOrderToMoySklad){
                $kaspiOrders->add($creatingOrder->orderId,$creatingOrder->orderExtId,'created');

                file_put_contents(__DIR__ . '/../logs/kaspi/kaspiCreateOrders.txt', date('d.m.Y H:i') . PHP_EOL . 'CREATING OBJECT:: ' . PHP_EOL . print_r($creatingOrder,true) . PHP_EOL . PHP_EOL,FILE_APPEND);

                $creatingOrder->autoorder = true;

                $creatingOrderMS = $moysklad->createOrder($creatingOrder,'kaspi',$shopkey);

                if(property_exists($creatingOrderMS,'errors')){
                  $errorsStr = '';
                  foreach ($creatingOrderMS->errors as $error) {
                    $errorsStr .= $error->error . PHP_EOL;
                  }
                  $telegram->sendTelegramMessage('Ошибка создания заказа Kaspi (' .$shopkey . ') #' . $order->attributes->code . '. Ответ МойСклад:' . PHP_EOL . $errorsStr, 'kaspi');
                }
                else {
                  $setStatus = $kaspi->setKaspiOrderStatus($creatingOrder,'ACCEPTED_BY_MERCHANT',$shopkey);
                  $telegram->sendTelegramMessage('Заказ Kaspi (' . $shopkey . ') #' . $order->attributes->code . ' успешно добавлен в МойСклад.', 'kaspi');
                }
              }
            }

            // ------ EXISTING ORDER ------
            else {
              $moyskladOrder = $moyskladOrders[0];
            }

            sleep(3); // Для контроля блокировки от Мойсклад
          }
        }
      }
    }

    public function actionCheckfinishedorders() // Проверка завершения заказов Каспи
    {
      $moysklad     = new Moysklad();
      $kaspi        = new Kaspi();
      $kaspiOrders  = new KaspiOrders();
      $website      = new Website();
      $telegram     = new Telegram();
      $whatsapp     = new Whatsapp();

      $kaspiShops = Yii::$app->params['moysklad']['kaspiProjects'];

      foreach ($kaspiShops as $shopkey => $shopid) {
        $orders = $kaspi->getKaspiOrders($shopkey,'ARCHIVE','COMPLETED');

        foreach ($orders->data as $order) {
          $orderCode    = $order->attributes->code;
          $sentToClient = $kaspiOrders->isReviewAlreadySent($orderCode);

          if(!$sentToClient){

            $customer     = $order->attributes->customer;
            $productsData = $kaspi->getKaspiOrderProducts($order->id,$shopkey);
            $link         = false;
            $orderProduct = $productsData->data[0];

            $productWebsiteData = $website->getProductWebDataBySku($orderProduct->attributes->offer->code);

            if(!empty(trim($productWebsiteData->kaspi_code))){
              $link = 'https://kaspi.kz/shop/review/productreview?orderCode=' . $order->attributes->code . '&productCode=' . trim($productWebsiteData->kaspi_code) . '&rating=5';
            }

            if($link){
              $messageInfo          = (object)array();
              $messageInfo->name    = (empty($customer->name)) ? $customer->firstName : $customer->name;
              $messageInfo->link    = $link;
              $messageInfo->orderid = $order->attributes->code;

              $customer->cellPhone  = '+7' . $customer->cellPhone;

              $sendWhatsappMessage  = true;
              switch($shopkey){
                case 'accio':
                  $waTemplate = 'set_kaspi_opinion_by_client_with_buttons_9';
                  break;
                case 'ital':
                  $waTemplate = 'set_italfoods_kaspi_opinion_by_client_with_buttons_2';
                  $sendWhatsappMessage = false;
                  break;
                case 'tutto':
                  $waTemplate = 'set_kaspi_opinion_by_client_with_buttons_9';
                  $sendWhatsappMessage = false;
                  break;
              }

              $sendPulseWhatsappMessage   = $whatsapp->sendWhatsappMessage($messageInfo,$customer->cellPhone,$waTemplate,$shopid,$sendWhatsappMessage);
              if($sendPulseWhatsappMessage['success']){
                $checkPulseWhatsappMessage  = $whatsapp->checkWhatsappSendpulseMessage($sendPulseWhatsappMessage);
              }

              if($sendPulseWhatsappMessage){
                if($sendPulseWhatsappMessage['success'] == 1){
                  $dbOrder = KaspiOrders::findByCode($order->attributes->code);
                  if ($dbOrder) {
                      $dbOrder->markSentToClient();
                  }

                  switch($shopkey){
                    case 'tutto':
                    case 'ital':
                      break;
                    default:
                      $telegram->sendTelegramMessage('Клиент ' . $customer->name . ' получил WhatsApp-сообщение с просьбой оставить отзыв на товар ' . $productWebsiteData->title . ' в заказе ' . $order->attributes->code . ' (' . $shopkey . ')' . '.', 'kaspi');
                  }
                }
              }
            }


          }
        }
      }

    }

    public function actionCheckcancelledorders() // Проверка отмененных заказов
    {
      $moysklad     = new Moysklad();
      $kaspi        = new Kaspi();
      $kaspiOrders  = new KaspiOrders();
      $website      = new Website();
      $telegram     = new Telegram();
      $whatsapp     = new Whatsapp();

      $kaspiShops = Yii::$app->params['moysklad']['kaspiProjects'];

      foreach ($kaspiShops as $shopkey => $shopid) {

        $orders1 = $kaspi->getKaspiOrders($shopkey,'ARCHIVE','CANCELLED','-10 hours');
        $orders2 = $kaspi->getKaspiOrders($shopkey,'NEW','CANCELLED','-10 hours');
        $orders3 = $kaspi->getKaspiOrders($shopkey,'SIGN_REQUIRED','CANCELLED','-10 hours');
        $orders4 = $kaspi->getKaspiOrders($shopkey,'PICKUP','CANCELLED','-10 hours');
        $orders5 = $kaspi->getKaspiOrders($shopkey,'KASPI_DELIVERY','CANCELLED','-10 hours');
        $orders6 = $kaspi->getKaspiOrders($shopkey,'DELIVERY','CANCELLED','-10 hours');
        $orders7 = $kaspi->getKaspiOrders($shopkey,'ARCHIVE','CANCELLING','-10 hours');
        $orders8 = $kaspi->getKaspiOrders($shopkey,'NEW','CANCELLING','-10 hours');
        $orders9 = $kaspi->getKaspiOrders($shopkey,'SIGN_REQUIRED','CANCELLING','-10 hours');
        $orders10 = $kaspi->getKaspiOrders($shopkey,'PICKUP','CANCELLING','-10 hours');
        $orders11 = $kaspi->getKaspiOrders($shopkey,'KASPI_DELIVERY','CANCELLING','-10 hours');
        $orders12 = $kaspi->getKaspiOrders($shopkey,'DELIVERY','CANCELLING','-10 hours');
        $ordersAll= array_merge($orders1->data,$orders2->data,$orders3->data,$orders4->data,$orders5->data,$orders6->data,$orders7->data,$orders8->data,$orders9->data,$orders10->data,$orders11->data,$orders12->data);

        foreach ($ordersAll as $order) {

          $code = (string)($order->attributes->code ?? '');

          file_put_contents(__DIR__ . '/../logs/kaspi/kaspiCancelledOrders.txt', date('d.m.Y H:i') . PHP_EOL . print_r($code,true) . PHP_EOL . PHP_EOL,FILE_APPEND);
          file_put_contents(__DIR__ . '/../logs/kaspi/kaspiCancelledOrders.txt', date('d.m.Y H:i') . PHP_EOL . print_r($order,true) . PHP_EOL . PHP_EOL,FILE_APPEND);

          if($order->attributes->status == 'CANCELLED'){} else { continue; }

          $dbOrder = KaspiOrders::findByCode($code);

          if (!$dbOrder) {
            continue;
          }

          if ($dbOrder->status === 'cancelled') {
            continue;
          }

          $prevStatus = (string)$dbOrder->status;

          $ordersMsInfo = $moysklad->checkOrderInMoySkladByMarketplaceCode($code);
          if (!$ordersMsInfo) {
            continue;
          }


          foreach ($ordersMsInfo as $iorder) {
              $demandsList = $iorder->demands ?? [];
              foreach ($demandsList as $demand) {

                  $demandId = '';
                  if (isset($demand->meta->href)) {
                      $demandId = basename((string)$demand->meta->href);
                  }
                  if ($demandId === '') {
                      continue;
                  }

                  $setTgMessage = false;
                  $returnType   = null;

                  switch ($prevStatus) { 
                      case 'created': // Без чека - Возврат на склад
                          $demandStateMeta = $moysklad->buildStateMeta(
                              'demand',
                              Yii::$app->params['moysklad']['demandUpdateHandler']['stateDemandReturnNoCheck']
                          );
                          $moysklad->updateDemandState($demandId, $demandStateMeta);
                          $returnType   = 'Без чека - Возврат на склад';
                          $setTgMessage = true;

                          file_put_contents(
                              __DIR__ . '/../logs/kaspi/kaspiCancelledOrders.txt',
                              date('d.m.Y') . PHP_EOL . 'CREATED' . PHP_EOL . print_r($order, true) . PHP_EOL . PHP_EOL,
                              FILE_APPEND
                          );
                          break;

                      case 'assemble': // Провести возврат
                          $demandStateMeta = $moysklad->buildStateMeta(
                              'demand',
                              Yii::$app->params['moysklad']['demandUpdateHandler']['stateDemandDoReturn']
                          );
                          $moysklad->updateDemandState($demandId, $demandStateMeta);
                          $returnType   = 'Провести возврат';
                          $setTgMessage = true;

                          file_put_contents(
                              __DIR__ . '/../logs/kaspi/kaspiCancelledOrders.txt',
                              date('d.m.Y') . PHP_EOL . 'ASSEMBLED' . PHP_EOL . print_r($order, true) . PHP_EOL . PHP_EOL,
                              FILE_APPEND
                          );
                          break;

                      default:
                          // если хочешь — можно уведомлять и по другим статусам
                          // $returnType = 'Отменён (статус в БД: ' . $prevStatus . ')';
                          // $setTgMessage = true;
                          break;
                  }

                  $dbOrder->updateStatus('cancelled');

                  if ($setTgMessage) {
                      $tgMessage  = 'Требуется оформить возврат для заказа Каспи #' . $code . PHP_EOL;
                      $tgMessage .= 'Тип возврата - ' . $returnType . PHP_EOL;
                      $tgMessage .= 'Магазин Каспи - ' . $shopkey;

                      $res = $telegram->sendTelegramMessage($tgMessage, 'cancelled');

                      file_put_contents(
                          __DIR__ . '/../logs/kaspi/kaspiCancelledOrders.txt',
                          'TG_RES ' . $code . ' :: ' . print_r($res, true) . PHP_EOL . str_repeat('-', 60) . PHP_EOL,
                          FILE_APPEND
                      );
                  }
              }
          }







          // if($dbOrder){
          //   $ordersMsInfo = $moysklad->checkOrderInMoySkladByMarketplaceCode($code);
          //
          //   if($ordersMsInfo){
          //     foreach ($ordersMsInfo as $iorder) {
          //       $demandsList = $iorder->demands;
          //
          //       foreach ($demandsList as $demand) {
          //         $demandId     = basename($demand->meta->href);
          //         $setTgMessage = false;
          //
          //         switch($dbOrder->status){
          //           case 'created': // 0ba2e09c-cda1-11eb-0a80-03110030c70c - статус Без чека - Возврат на склад
          //             $demandStateMeta = $moysklad->buildStateMeta('demand', YII::$app->params['moysklad']['demandUpdateHandler']['stateDemandReturnNoCheck']);
          //             $moysklad->updateDemandState($demandId,$demandStateMeta);
          //             $returnType = 'Без чека - Возврат на склад';
          //             file_put_contents(__DIR__ . '/../logs/kaspi/kaspiCancelledOrders.txt', date('d.m.Y') . PHP_EOL . 'CREATED' . PHP_EOL . print_r($order,true) . PHP_EOL . PHP_EOL,FILE_APPEND);
          //             $setTgMessage = true;
          //             break;
          //           case 'assemble': // 2a6c9db5-a7c4-11ed-0a80-10870015e950 - статус Провести возврат
          //             $demandStateMeta = $moysklad->buildStateMeta('demand', YII::$app->params['moysklad']['demandUpdateHandler']['stateDemandDoReturn']);
          //             $moysklad->updateDemandState($demandId,$demandStateMeta);
          //             $returnType = 'Провести возврат';
          //             file_put_contents(__DIR__ . '/../logs/kaspi/kaspiCancelledOrders.txt', date('d.m.Y') . PHP_EOL . 'ASSEMBLED' . PHP_EOL . print_r($order,true) . PHP_EOL . PHP_EOL,FILE_APPEND);
          //             $setTgMessage = true;
          //             break;
          //         }
          //
          //         $dbOrder->updateStatus('cancelled');
          //
          //         if($setTgMessage){
          //           $tgMessage = 'Требуется оформить возврат для заказа Каспи #' . $order->attributes->code . PHP_EOL;
          //           $tgMessage .= 'Тип возврата - ' . $returnType . PHP_EOL;
          //           $tgMessage .= 'Магазин Каспи - ' . $shopkey;
          //
          //           $telegram->sendTelegramMessage($tgMessage, 'cancelled');
          //         }
          //       }
          //
          //     }
          //   }
          // }

        }
      }
    }





    /*
    Закрытие смен всех касс.
    Функция есть, но нигде не включена.
    */
    public function actionCloseshifts()
    {
      $cashregisters = YII::$app->params['ukassa']['accounts'];

      foreach ($cashregisters as $cashregister => $data) {
        $res = CashRegister::closeZShiftAndSave($cashregister);
      }
    }
}
