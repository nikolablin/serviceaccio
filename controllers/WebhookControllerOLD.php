<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\filters\VerbFilter;
use app\models\Moysklad;
use app\models\OrdersConfigTable;
use app\models\Orders;
use app\models\OrdersProducts;
use app\models\OrdersClients;
use app\models\OrdersDemands;

class WebhookController extends Controller
{
    // Для вебхуков обычно отключают CSRF
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    // вебхуки МойСклада приходят POST-ом
                    'index' => ['post'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $request = Yii::$app->request;

        // // 1) Проверяем секретный токен в GET-параметре
        // $token = $request->get('token');
        // if ($token !== self::SECRET_TOKEN) {
        //     Yii::warning('Webhook: wrong token from IP ' . $request->userIP, __METHOD__);
        //     throw new ForbiddenHttpException('Forbidden');
        // }

        // // 2) Доп. защита: проверяем User-Agent (его МойСклад ставит в вебхуках)
        // $ua = $request->userAgent;
        // if (stripos($ua, 'MoySklad webhook touch agent 2.0') === false) {
        //     Yii::warning('Webhook: wrong UA ' . $ua . ' from IP ' . $request->userIP, __METHOD__);
        //     throw new ForbiddenHttpException('Forbidden');
        // }

        return true;
    }

    public function actionIndex()
    {
        $rawBody = Yii::$app->request->getRawBody();
        $data = json_decode($rawBody, true);

        // тут сохраняешь/обрабатываешь вебхук
        Yii::info('MoySklad webhook: ' . $rawBody, __METHOD__);

        // МойСклад ждёт 200/204
        return 'ok';
    }

    /* Вебхуки Мойсклад */

    public function actionCreatecustomerorder() // Создание нового заказа / Создание отгрузки
    {
      // Обработка созданных в системе заказов, при определенных проектах автоматическая установка значений полей
      $moysklad = new Moysklad();

      $actualProjects = $moysklad->getActualProjects();

      $data = file_get_contents('php://input');
      $data = json_decode($data);
      file_put_contents(__DIR__ . '/createcustomerorder.txt',print_r($data,true) . PHP_EOL,FILE_APPEND);

      foreach ($data->events as $event) {
        if($event->meta->type == 'customerorder' AND $event->action == 'CREATE'){
          $MSOrder          = $moysklad->getHrefData($event->meta->href . '?expand=agent,project,organization,store,state');
          file_put_contents(__DIR__ . '/createcustomerorder.txt', print_r($MSOrder,true) . PHP_EOL,FILE_APPEND);
          $positionsHref = $MSOrder->positions->meta->href ?? null;
          if ($positionsHref) {
            $MSOrder->positions = $moysklad->getHrefData($positionsHref . '?expand=assortment');
          }
          $MSOrderProjectId = basename($MSOrder->project->meta->href);
          $actionType = 2;

          if(in_array($MSOrderProjectId,$actualProjects)){
            switch($MSOrderProjectId){
              // Пока отключены
              case '6b625db1-d270-11f0-0a80-1512001756b3': // 💎 Юридическое лицо
              case '8fe86883-d275-11f0-0a80-15120017c4b6': // 🔥 Store
              case 'c4bd7d52-d276-11f0-0a80-17910017cc0c': // ♥️ Accio Store
                break;
              default:
                $actionType = 1;
                $configData = OrdersConfigTable::findOne(['project' => $MSOrderProjectId]);

                if ($configData === null) {
                  file_put_contents(__DIR__ . '/createcustomerorder.txt', 'Не найдена конфигурация под проект, создан как есть' . PHP_EOL,FILE_APPEND);
                }
                else {
                  // Обновляем поля заказа
                  $updateOrder = $moysklad->updateOrderWithConfig($MSOrder->id,$configData);
                  $positionsHref = $updateOrder->positions->meta->href ?? null;
                  if ($positionsHref) {
                    $updateOrder->positions = $moysklad->getHrefData($positionsHref . '?expand=assortment');
                  }
                  $MSOrder = $updateOrder;
                }
            }
          }

          // 3) Сохраняем в локальные таблицы (orders + client + products)
          try {
              $orderId = Orders::upsertFromMs($MSOrder, $MSOrderProjectId, $actionType);
              OrdersClients::upsertFromMs($orderId, $MSOrder);
              OrdersProducts::syncFromMs($orderId, $MSOrder);

              // Создаем отгрузку
              // ✅ Создаем отгрузку ТОЛЬКО если заказ = "Подтвержден / К отправке" или "Счет выставлен"
              $ALLOW_DEMAND_STATE = ['d3e01366-75ca-11eb-0a80-02590037e535','6d4d6565-79a4-11eb-0a80-07bf001ea079'];

              $orderStateHref = $MSOrder->state->meta->href ?? null;
              $orderStateId   = $orderStateHref ? basename($orderStateHref) : null;

              if (in_array($orderStateId, $ALLOW_DEMAND_STATE, true)) {
                $demand = $moysklad->upsertDemandFromOrder($MSOrder,$orderId,$configData);
                file_put_contents(__DIR__ . '/createcustomerorder.txt',
                  "DEMAND CREATED for order {$MSOrder->id}\n",
                  FILE_APPEND
                );
              }
          } catch (\Throwable $e) {
              file_put_contents(__DIR__ . '/createcustomerorder.txt', $e->getMessage() . PHP_EOL, FILE_APPEND);
          }
        }
      }

      return $this->render('createcustomerorder');
    }

    public function actionCreatedemand()
    {
      $data1 = file_get_contents('php://input');
      $data2 = $_POST;
      file_put_contents(__DIR__ . '/createdemand.txt',print_r($data1,true) . PHP_EOL,FILE_APPEND);
      file_put_contents(__DIR__ . '/createdemand.txt',print_r($data2,true) . PHP_EOL . PHP_EOL,FILE_APPEND);
      return $this->render('createdemand');
    }

    public function actionUpdatecustomerorder() // Обновление заказа / Отгрузки / Позиций
    {
        $moysklad = new Moysklad();

        $raw = file_get_contents('php://input');
        file_put_contents(__DIR__ . '/updatecustomerorder.txt', $raw . PHP_EOL, FILE_APPEND);

        $payload = json_decode($raw);
        if (!$payload || empty($payload->events)) {
            return 'ok';
        }

        // ORDER → DEMAND (status mapping)
        $STATE_MAP = [
            'd3e01366-75ca-11eb-0a80-02590037e535' => 'db67917a-5717-11eb-0a80-079c002b43eb', // Подтвержден / К отправке → К отгрузке
            '6d4d6565-79a4-11eb-0a80-07bf001ea079' => '2ecdeb7b-799b-11eb-0a80-00de001d8587', // Счет выставлен → Счет выставлен
        ];

        // Статус, при котором разрешено создавать / обновлять отгрузку по товарам
        $ALLOW_DEMAND_STATE = ['d3e01366-75ca-11eb-0a80-02590037e535','6d4d6565-79a4-11eb-0a80-07bf001ea079'];

        foreach ($payload->events as $event) {

            if (
                ($event->meta->type ?? null) !== 'customerorder'
                || ($event->action ?? null) !== 'UPDATE'
            ) {
                continue;
            }

            try {
                $orderHref = $event->meta->href ?? null;
                $msOrderId = $orderHref ? basename($orderHref) : null;
                if (!$msOrderId) {
                    continue;
                }

                /**
                 * 1) Загружаем заказ с состоянием и позициями
                 */
                $order = $moysklad->getHrefData($orderHref . '?expand=state,positions');

                if (!empty($order->positions->meta->href)) {
                    $order->positions = $moysklad->getHrefData(
                        $order->positions->meta->href . '?expand=assortment'
                    );
                }

                $orderStateHref = $order->state->meta->href ?? null;
                if (!$orderStateHref) {
                    continue;
                }

                $orderStateId = basename($orderStateHref);

                /**
                 * 2) Находим связь с отгрузкой (если есть)
                 */
                $link = OrdersDemands::findOne([
                    'moysklad_order_id' => $msOrderId,
                ]);

                /**
                 * 3) СИНХРОНИЗАЦИЯ ПОЗИЦИЙ (CREATE / UPDATE DEMAND)
                 *    — только если заказ в нужном статусе
                 *    — позиции ПОЛНОСТЬЮ перезаписываются
                 */
                 if (in_array($orderStateId, $ALLOW_DEMAND_STATE, true)) {

                    $skipPositionsSync = false;
                    if (
                        $link
                        && !empty($link->block_demand_until)
                        && strtotime($link->block_demand_until) > time()
                    ) {
                        $skipPositionsSync = true;
                    }

                    if (!$skipPositionsSync) {

                        $projectId = basename($order->project->meta->href ?? '');
                        $configData = $projectId
                            ? OrdersConfigTable::findOne(['project' => $projectId])
                            : null;

                        $moysklad->upsertDemandFromOrder(
                            $order,
                            $link ? $link->order_id : null,
                            $configData,
                            [
                                'sync_positions' => true, // ПОЛНАЯ перезапись позиций
                            ]
                        );

                        if ($link) {
                            $link->block_demand_until = date('Y-m-d H:i:s', time() + 10);
                            $link->updated_at = date('Y-m-d H:i:s');
                            $link->save(false);
                        }

                        file_put_contents(
                            __DIR__ . '/updatecustomerorder.txt',
                            ($link ? 'DEMAND POSITIONS UPDATED' : 'DEMAND CREATED')
                            . " for order {$msOrderId}\n",
                            FILE_APPEND
                        );
                    }
                }

                /**
                 * 4) СИНХРОНИЗАЦИЯ СТАТУСА ОТГРУЗКИ
                 *    — по жёсткому маппингу
                 *    — НЕ зависит от block_demand_until
                 */
                if (!isset($STATE_MAP[$orderStateId])) {
                    continue;
                }

                $demandStateId   = $STATE_MAP[$orderStateId];
                $demandStateMeta = $moysklad->buildStateMeta('demand', $demandStateId);

                $links = OrdersDemands::find()
                    ->where(['moysklad_order_id' => $msOrderId])
                    ->all();

                foreach ($links as $link) {
                    $msDemandId = $link->moysklad_demand_id ?? null;
                    if (!$msDemandId) {
                        continue;
                    }

                    $res = $moysklad->updateDemandState($msDemandId, $demandStateMeta);

                    if (is_array($res) && empty($res['ok'])) {
                        file_put_contents(
                            __DIR__ . '/updatecustomerorder.txt',
                            "DEMAND STATE FAIL demand={$msDemandId} http={$res['code']} resp={$res['raw']}\n",
                            FILE_APPEND
                        );
                        continue;
                    }

                    $link->updated_at = date('Y-m-d H:i:s');
                    $link->save(false);

                    file_put_contents(
                        __DIR__ . '/updatecustomerorder.txt',
                        "DEMAND STATE UPDATED demand={$msDemandId} <= {$demandStateId} (order={$msOrderId})\n",
                        FILE_APPEND
                    );
                }

            } catch (\Throwable $e) {
                file_put_contents(
                    __DIR__ . '/updatecustomerorder.txt',
                    "ERROR: {$e->getMessage()}\n",
                    FILE_APPEND
                );
            }
        }

        return 'ok';
    }

    public function actionUpdatedemand() // Обновление отгрузки → заказ / Позиций
    {
        $moysklad = new Moysklad();

        $raw = file_get_contents('php://input');
        file_put_contents(__DIR__ . '/updatedemand.txt', $raw . PHP_EOL, FILE_APPEND);

        $payload = json_decode($raw);
        if (!$payload || empty($payload->events)) {
            return 'ok';
        }

        // DEMAND → ORDER (статусы)
        $STATE_MAP = [
            'eeed10b7-51a2-11ec-0a80-02ee0032e089' => 'c4d8f685-a7c3-11ed-0a80-10870015dd4a', // Собран → Собран
            '732ffbde-0a19-11eb-0a80-055600083d2e' => '02482dd6-ee91-11ea-0a80-05f200074471', // Передан → Завершен
            '24d4a11f-8af4-11eb-0a80-0122002915d0' => '02482dd6-ee91-11ea-0a80-05f200074471', // Закрыт → Завершен
            '0ba2e09c-cda1-11eb-0a80-03110030c70c' => '02482e52-ee91-11ea-0a80-05f200074472', // 🚫 Без чека → Возврат
            'aa7acdbc-a7c9-11ed-0a80-0c71001732ca' => '02482e52-ee91-11ea-0a80-05f200074472', // Возврат
        ];

        foreach ($payload->events as $event) {

            if (
                ($event->meta->type ?? null) !== 'demand'
                || ($event->action ?? null) !== 'UPDATE'
            ) {
                continue;
            }

            try {
                $demandHref = $event->meta->href ?? '';
                $msDemandId = $demandHref ? basename($demandHref) : null;
                if (!$msDemandId) continue;

                // 1️⃣ Загружаем отгрузку со статусом и позициями
                $demand = $moysklad->getHrefData($demandHref . '?expand=state,positions');

                if (!empty($demand->positions->meta->href)) {
                    $demand->positions = $moysklad->getHrefData(
                        $demand->positions->meta->href . '?expand=assortment'
                    );
                }

                $demandStateHref = $demand->state->meta->href ?? null;
                if (!$demandStateHref) continue;

                $demandStateId = basename($demandStateHref);

                // 2️⃣ Ищем связанные заказы
                $links = OrdersDemands::find()
                    ->where(['moysklad_demand_id' => $msDemandId])
                    ->all();

                if (!$links) {
                    file_put_contents(__DIR__ . '/updatedemand.txt',
                        "Нет orders_demands для demand {$msDemandId}\n",
                        FILE_APPEND
                    );
                    continue;
                }

                foreach ($links as $link) {

                    $msOrderId = $link->moysklad_order_id ?? null;
                    if (!$msOrderId) continue;

                    $orderModel = Orders::find()
                        ->where(['moysklad_id' => $msOrderId])
                        ->one();

                    /*
                     * =========================
                     * 3️⃣ СИНК ПОЗИЦИЙ (DEMAND → ORDER)
                     * =========================
                     */
                    if (
                        !$orderModel ||
                        empty($orderModel->block_order_until) ||
                        strtotime($orderModel->block_order_until) <= time()
                    ) {

                        $positions = [];

                        foreach ($demand->positions->rows ?? [] as $row) {
                            $positions[] = [
                                'quantity' => $row->quantity,
                                'price'    => $row->price,
                                'assortment' => [
                                    'meta' => $row->assortment->meta,
                                ],
                            ];
                        }

                        if ($orderModel) {
                            $orderModel->block_order_until = date('Y-m-d H:i:s', time() + 10);
                            $orderModel->save(false);
                        }

                        $resPos = $moysklad->updateOrderPositions($msOrderId, $positions);

                        if (!$resPos['ok']) {
                            file_put_contents(__DIR__ . '/updatedemand.txt',
                                "ORDER POS FAIL order={$msOrderId} http={$resPos['code']} resp={$resPos['raw']}\n",
                                FILE_APPEND
                            );
                        } else {
                            file_put_contents(__DIR__ . '/updatedemand.txt',
                                "ORDER POS UPDATED order={$msOrderId}\n",
                                FILE_APPEND
                            );
                        }
                    } else {
                        file_put_contents(__DIR__ . '/updatedemand.txt',
                            "SKIP order positions (loop-guard) order={$msOrderId}\n",
                            FILE_APPEND
                        );
                    }

                    /*
                     * =========================
                     * 4️⃣ СИНК СТАТУСА
                     * =========================
                     */
                    if (!isset($STATE_MAP[$demandStateId])) {
                        continue;
                    }

                    $orderStateId   = $STATE_MAP[$demandStateId];
                    $orderStateMeta = $moysklad->buildStateMeta('customerorder', $orderStateId);

                    if (
                        $orderModel &&
                        !empty($orderModel->block_order_until) &&
                        strtotime($orderModel->block_order_until) > time()
                    ) {
                        file_put_contents(__DIR__ . '/updatedemand.txt',
                            "SKIP order state (loop-guard) order={$msOrderId}\n",
                            FILE_APPEND
                        );
                        continue;
                    }

                    if ($orderModel) {
                        $orderModel->block_order_until = date('Y-m-d H:i:s', time() + 10);
                        $orderModel->save(false);
                    }

                    $res = $moysklad->updateOrderState($msOrderId, $orderStateMeta);

                    if (is_array($res) && empty($res['ok'])) {
                        file_put_contents(__DIR__ . '/updatedemand.txt',
                            "ORDER STATE FAIL order={$msOrderId} http={$res['code']} resp={$res['raw']}\n",
                            FILE_APPEND
                        );
                    } else {
                        file_put_contents(__DIR__ . '/updatedemand.txt',
                            "ORDER STATE UPDATED order={$msOrderId} <= {$orderStateId}\n",
                            FILE_APPEND
                        );
                    }

                    $link->updated_at = date('Y-m-d H:i:s');
                    $link->save(false);
                }

            } catch (\Throwable $e) {
                file_put_contents(__DIR__ . '/updatedemand.txt',
                    "ERROR: {$e->getMessage()}\n",
                    FILE_APPEND
                );
            }
        }

        return 'ok';
    }

    public function actionDeletecustomerorder()
    {
      $data1 = file_get_contents('php://input');
      $data2 = $_POST;
      file_put_contents(__DIR__ . '/deletecustomerorder.txt',print_r($data1,true) . PHP_EOL,FILE_APPEND);
      file_put_contents(__DIR__ . '/deletecustomerorder.txt',print_r($data2,true) . PHP_EOL . PHP_EOL,FILE_APPEND);
      return $this->render('deletecustomerorder');
    }

    public function actionDeletedemand()
    {
      $data1 = file_get_contents('php://input');
      $data2 = $_POST;
      file_put_contents(__DIR__ . '/deletedemand.txt',print_r($data1,true) . PHP_EOL,FILE_APPEND);
      file_put_contents(__DIR__ . '/deletedemand.txt',print_r($data2,true) . PHP_EOL . PHP_EOL,FILE_APPEND);
      return $this->render('deletedemand');
    }

    /* EOF Вебхуки Мойсклад */

}
