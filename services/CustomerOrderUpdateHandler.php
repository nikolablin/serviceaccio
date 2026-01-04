<?php

namespace app\services;

use Yii;
use app\models\Moysklad;
use app\models\Orders;
use app\models\OrdersClients;
use app\models\OrdersProducts;
use app\models\OrdersDemands;
use app\models\OrdersMoneyin;
use app\models\OrdersInvoicesOut;

class CustomerOrderUpdateHandler
{
    public function handle(object $event): void
    {
        if (
            ($event->meta->type ?? null) !== 'customerorder'
            || ($event->action ?? null) !== 'UPDATE'
        ) {
            return;
        }

        $moysklad = new Moysklad();

        /**
         * 1️⃣ Загружаем заказ из МС (project,state,positions,paymentType,attributes)
         */
        $orderHref = $event->meta->href ?? null;
        if (!$orderHref) {
            return;
        }

        $order = $moysklad->getHrefData(
            $orderHref . '?expand=project,state,positions,paymentType,attributes'
        );

        if (empty($order->id)) {
            // если getHrefData вернул что-то странное
            return;
        }

        if (empty($order->project->meta->href)) {
            return;
        }

        $projectId = basename($order->project->meta->href);

        /**
         * 2️⃣ Конфиг проекта (через resolver)
         */
        $configData = (new \app\services\OrdersConfigResolver())->resolve($order);
        if (!$configData) {
            return;
        }

        /**
         * 3️⃣ Подгружаем позиции
         */
        $positionsHref = $order->positions->meta->href ?? null;
        if ($positionsHref) {
            $order->positions = $moysklad->getHrefData(
                $positionsHref . '?expand=assortment'
            );
        }

        /**
         * 4️⃣ Обновляем заказ в МС по конфигу (если нужно), убираем статус
         */
        unset($configData['status']);
        $updated = $moysklad->updateOrderWithConfig($order->id, $configData);
        if ($updated && !empty($updated->id)) {
            $ph = $updated->positions->meta->href ?? null;
            if ($ph) {
                $updated->positions = $moysklad->getHrefData(
                    $ph . '?expand=assortment'
                );
            }
            $order = $updated;
        }

        /**
         * ✅ текущий статус заказа (для истории переходов)
         */
        $stateHref = $order->state->meta->href ?? null;
        $stateId   = $stateHref ? basename($stateHref) : null;

        // старый статус (после upsert уже есть запись)
        $oldStateId = Orders::find()
            ->select('moysklad_state_id')
            ->where(['moysklad_id' => (string)$order->id])
            ->scalar();

        /**
         * 5️⃣ Сохраняем заказ локально
         */
        $orderId = Orders::upsertFromMs($order, $projectId, 1);
        OrdersClients::upsertFromMs($orderId, $order);
        OrdersProducts::syncFromMs($orderId, $order);

        if($stateId === Yii::$app->params['moysklad']['deleteAllFromOrderState']){
          file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
              "ROLLBACK start order={$order->id}\n",
              FILE_APPEND
          );

          // 1) Удаляем money-in (paymentin/cashin) в МС + локально
          $moneyRows = OrdersMoneyin::find()
              ->where(['moysklad_order_id' => (string)$order->id])
              ->all();

          foreach ($moneyRows as $mr) {
              $docId = $mr->moysklad_doc_id ?? null;
              $type  = $mr->doc_type ?? null;

              if ($docId && $type === 'paymentin') {
                  $res = $moysklad->deletePaymentIn($docId);
              } elseif ($docId && $type === 'cashin') {
                  $res = $moysklad->deleteCashIn($docId);
              } else {
                  $res = null;
              }
              $mr->delete();

              file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
                  "ROLLBACK moneyin type={$type} id={$docId} ok=" . (is_array($res) ? (int)$res['ok'] : -1) . " http=" . (is_array($res) ? $res['code'] : '') . "\n",
                  FILE_APPEND
              );
          }

          // 2) Удаляем invoiceout в МС + локально
          $invLink = OrdersInvoicesOut::findOne(['moysklad_order_id' => (string)$order->id]);
          if ($invLink) {
              $invId = $invLink->moysklad_invoiceout_id ?? null;
              if ($invId) {
                  $res = $moysklad->deleteInvoiceOut($invId);

                  file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
                      "ROLLBACK invoiceout id={$invId} ok=" . (int)$res['ok'] . " http={$res['code']}\n",
                      FILE_APPEND
                  );
              }
              $invLink->delete();
          }

          // 3) Удаляем demand в МС + локально
          $demLink = OrdersDemands::findOne(['moysklad_order_id' => (string)$order->id]);
          if ($demLink) {
              $demId = $demLink->moysklad_demand_id ?? null;
              if ($demId) {
                  $res = $moysklad->deleteDemand($demId);

                  file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
                      "ROLLBACK demand id={$demId} ok=" . (int)$res['ok'] . " http={$res['code']}\n",
                      FILE_APPEND
                  );
              }
              $demLink->delete();
          }

          file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
              "ROLLBACK done order={$order->id}\n",
              FILE_APPEND
          );

          // ✅ важно: дальше не продолжаем (иначе блоки создания снова накидают документы)
          return;
        }

        // обновим статус, если поменялся
        if ($stateId && $oldStateId !== $stateId) {
            Orders::updateAll(
                [
                    'moysklad_state_id' => $stateId,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ],
                ['id' => $orderId]
            );
        }

        /**
         * 6️⃣ Связь с отгрузкой (если есть)
         */
        $link = OrdersDemands::findOne([
            'moysklad_order_id' => (string)$order->id,
        ]);

        /**
         * 7️⃣ CREATE / UPDATE DEMAND (позиции)
         *     — только при разрешённом статусе
         */
        $allowStates = Yii::$app->params['moysklad']['allowDemandStates'] ?? [];

        if ($stateId && in_array($stateId, $allowStates, true)) {

            $skip = false;
            if (
                $link &&
                !empty($link->block_demand_until) &&
                strtotime($link->block_demand_until) > time()
            ) {
                $skip = true;
            }

            if (!$skip) {
                $demand = $moysklad->upsertDemandFromOrder(
                    $order,
                    $orderId,
                    $configData,
                    [
                        'sync_positions' => true,
                    ]
                );
                file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',print_r($demand,true) . PHP_EOL, FILE_APPEND);
                if ($link) {
                    $link->block_demand_until = date('Y-m-d H:i:s', time() + 10);
                    $link->updated_at = date('Y-m-d H:i:s');
                    $link->save(false);
                }
            }
        }

        /**
         * 8️⃣ СИНК СТАТУСА ОТГРУЗКИ (ORDER → DEMAND)
         */
        $stateMap = Yii::$app->params['moysklad']['stateMapOrderToDemand'] ?? [];

        if ($stateId && isset($stateMap[$stateId])) {

            $demandStateId   = $stateMap[$stateId];
            $demandStateMeta = $moysklad->buildStateMeta('demand', $demandStateId);

            $links = OrdersDemands::find()
                ->where(['moysklad_order_id' => (string)$order->id])
                ->all();

            foreach ($links as $lnk) {
                $msDemandId = $lnk->moysklad_demand_id ?? null;
                if (!$msDemandId) {
                    continue;
                }

                $res = $moysklad->updateDemandState($msDemandId, $demandStateMeta);

                if (is_array($res) && empty($res['ok'])) {
                    continue;
                }

                $lnk->updated_at = date('Y-m-d H:i:s');
                $lnk->save(false);
            }
        }

        $invoiceIssuedState = Yii::$app->params['moysklad']['orderStateInvoiceIssued'] ?? null;
        $invoiceOutState    = Yii::$app->params['moysklad']['invoiceOutStateIssued'] ?? null;

        if ($invoiceIssuedState && $stateId === $invoiceIssuedState) {

            $existsLocal = OrdersInvoicesOut::find()
                ->where(['moysklad_order_id' => (string)$order->id])
                ->exists();

            if (!$existsLocal) {

                $invoice = $moysklad->createInvoiceOutFromOrder($order, $configData);

                // ✅ добавь лог, если не создался
                if ($invoice === false) {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
                        "INVOICEOUT CREATE FAIL order={$order->id}\n",
                        FILE_APPEND
                    );
                }

                if ($invoice && !is_array($invoice) && !empty($invoice->id)) {

                    if ($invoiceOutState) {
                        $moysklad->updateInvoiceOutState(
                            (string)$invoice->id,
                            $moysklad->buildStateMeta('invoiceout', $invoiceOutState)
                        );
                    }

                    OrdersInvoicesOut::syncFromMsInvoiceOut(
                        $invoice,
                        $orderId,
                        (string)$order->id
                    );
                }
            }
        }

    }
}
