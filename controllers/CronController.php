<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\models\Kaspi;
use app\models\Moysklad;
use app\models\Website;
use app\models\Telegram;
use app\models\KaspiOrders;
use app\models\OrdersConfigTable;

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
      $kaspi        = new Kaspi();
      $kaspiOrders  = new KaspiOrders();
      $website      = new Website();
      $telegram     = new Telegram();

      $kaspiShops = $kaspi->getKaspiShops();

      foreach ($kaspiShops as $shopid) {

        $projectType    = $moysklad->getProjectByCode($shopid);
        $projectConfig  = OrdersConfigTable::findOne(['project' => $projectType]);

        $orders = $kaspi->getKaspiOrders($shopid,'NEW');

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
              $productsData                 = $kaspi->getKaspiOrderProducts($order->id,$shopid);
              $creatingOrder->products      = [];
              $creatingOrder->orderStatus   = $projectConfig->status;
              $creatingOrder->comment       = false;
              $orderPickupPointCity         = false;

              // PP names
              $pointsNames = $kaspi->getPointsTitles($shopid);

              // switch($shopid){
              //   case 'accio':
              //     $pp1name = 'Accio_PP1';
              //     $pp2name = 'Accio_PP2';
              //     $pp15name = 'Accio_PP15';
              //     break;
              //   case 'ItalFood':
              //     $pp1name = '30093069_PP1';
              //     $pp2name = '30093069_PP2';
              //     $pp15name = '30093069_PP15';
              //     break;
              //   case 'kasta':
              //     $pp1name = '30224658_PP1';
              //     $pp2name = '30224658_PP2';
              //     $pp15name = false;
              //     break;
              // }

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

              foreach ($productsData->data as $prdata) {
                $productInfo            = $kaspi->getKaspiLinkData('https://kaspi.kz/shop/api/v2/masterproducts/' . $prdata->relationships->product->data->id . '/merchantProduct',$shopid);
                $productWebSiteData     = $website->getProductWebDataBySku($productInfo->data->attributes->code);
                if($productWebSiteData){
                  $productRemainsInStock  = $moysklad->productRemainsCheckByArray($productInfo->data->attributes->code,$prdata->attributes->quantity,$moySkladRemains,$productWebSiteData->product_id);

                  foreach ($productRemainsInStock as $key => $remain) {
                    if($key == $orderPickupPointCity){
                      if($remain < $prdata->attributes->quantity){
                        $creatingOrder->orderStatus = '02482aa0-ee91-11ea-0a80-05f20007446d'; // Если беда с количеством, то создаем со статусом - Взять в работу
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
                  $creatingOrder->products[] = $prObj;
                }
                else {
                  $addOrderToMoySklad = false;
                  $telegram->sendTelegramMessage('Ошибка создания заказа Kaspi #' . $order->attributes->code . ' (' . $shopid . '). Не найден товар SKU - ' . $productInfo->data->attributes->code . '.', 'kaspi');
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

              // switch($shopid){
              //   case 'accio':
              //     $creatingOrder->organization    = '1e0488ad-0a26-11ec-0a80-05760004991d'; // ИП СПЕКТОРГ
              //     $creatingOrder->project         = '698bbf4d-7346-11eb-0a80-083400146e88'; // Kaspi project
              //     break;
              //   case 'ItalFood':
              //     $creatingOrder->organization    = '3bd63649-f257-11ea-0a80-005d003d9ee4'; // ИП ИталФуд
              //     $creatingOrder->project         = 'd4986e14-0931-11ef-0a80-0bd6000d967f'; // Kaspi 2 project
              //     break;
              //   case 'kasta':
              //     $creatingOrder->organization    = '640cb82e-82af-11ed-0a80-07fe00255908'; // ИП Accio Retail Store
              //     $creatingOrder->project         = '7b12e831-0817-11f0-0a80-165a0010ce66'; // Kaspi Kasta project
              //     break;
              // }

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

              $creatingOrder->kaspiOrderId    = $order->attributes->code;
              $creatingOrder->kaspiOrderExtId = $order->id;
              $creatingOrder->deliveryDate    = $kaspi->getKaspiDeliveryDate($order);
              $creatingOrder->deliveryTime    = ($creatingOrder->deliveryDate) ? $moysklad->getDeliveryTime($order) : false;

              $creatingOrder->paymentStatus   = $projectConfig->payment_status;
              $creatingOrder->paymentType     = $projectConfig->payment_type;
              $creatingOrder->fiscalBill      = $projectConfig->fiscal;
              $creatingOrder->cityStr         = '';
              $creatingOrder->address         = '';
              $creatingOrder->kaspiDeliveryCost = property_exists($order->attributes,'deliveryCost') ? (string)$order->attributes->deliveryCost : (string)0;

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
                        $creatingOrder->deliveryType = $projectConfig->delivery_service;
                      }
                      break;
                    case 'DELIVERY_PICKUP':
                      $creatingOrder->deliveryType = $projectConfig->delivery_service; // Zammler
                      break;
                    case 'DELIVERY_REGIONAL_PICKUP':
                      $creatingOrder->deliveryType = $projectConfig->delivery_service; // Zammler
                      break;
                    case 'DELIVERY_REGIONAL_TODOOR':
                      $creatingOrder->deliveryType = $projectConfig->delivery_service; // Zammler
                      break;
                  }
                  break;
                case false:
                  $creatingOrder->deliveryType = 'c45aea40-54cd-11ec-0a80-095800022a93'; // Самовывоз
                  break;
              }

              if($addOrderToMoySklad){
                $kaspiOrders->add($creatingOrder->kaspiOrderId,$creatingOrder->kaspiOrderExtId);
                $creatingOrderMS = $moysklad->createOrder($creatingOrder,'kaspi',$shopid);

                if(property_exists($creatingOrderMS,'errors')){
                  $errorsStr = '';
                  foreach ($creatingOrderMS->errors as $error) {
                    $errorsStr .= $error->error . PHP_EOL;
                  }
                  $telegram->sendTelegramMessage('Ошибка создания заказа Kaspi (' .$shopid . ') #' . $order->attributes->code . '. Ответ МойСклад:' . PHP_EOL . $errorsStr, 'kaspi');
                }
                else {
                  $kaspi->setKaspiOrderStatus($creatingOrder,'ACCEPTED_BY_MERCHANT',$shopid);
                  $telegram->sendTelegramMessage('Заказ Kaspi (' .$shopid . ') #' . $order->attributes->code . ' успешно добавлен в МойСклад.', 'kaspi');
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
}
