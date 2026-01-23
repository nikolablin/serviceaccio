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

        // Принудительно торможу на 2 секунды, чтобы второй вебхук не шарахнул по первому
        sleep(2);

        $moysklad = new Moysklad();

        /**
         * 1️⃣ Загружаем заказ из МС (project,state,positions,paymentType,attributes)
         */
        $orderHref = $event->meta->href ?? null;
        if (!$orderHref) {
            return;
        }

        $order = $moysklad->getHrefData(
            $orderHref . '?expand=project,state,positions,paymentType,attributes,store'
        );

        if (empty($order->id)) {
            // если getHrefData вернул что-то странное
            return;
        }

        if (empty($order->project->meta->href)) {
            return;
        }

        $projectId = basename($order->project->meta->href);
        $orderIsManual = $moysklad->isManualOrder($order); // Определение вручную созданного заказа

        /**
         * 2️⃣ Конфиг проекта (через resolver)
         */
        $configData = (new \app\services\OrdersConfigResolver())->resolve($order,$orderIsManual);
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

        $configData->status = false;
        $configData->delivery_service = false;
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
        $stateHref    = $order->state->meta->href ?? null;
        $stateId      = $stateHref ? basename($stateHref) : null;
        $isOrgProject = (Yii::$app->params['moysklad']['organizationProject'] !== '' && (string)$projectId === Yii::$app->params['moysklad']['organizationProject']);

        // старый статус (после upsert уже есть запись)
        $oldStateId = Orders::find()
            ->select('moysklad_state_id')
            ->where(['moysklad_id' => (string)$order->id])
            ->scalar();

        /**
         * 5️⃣ Сохраняем заказ локально
         */
        $orderId = Orders::upsertFromMs($order, $projectId, (($orderIsManual) ? 2 : 1));
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
                      "ROLLBACK demand id={$demId} ok=" . (int)$res['ok'] . " http={$res['code']}\n" .
                      print_r($res,true),
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
            // 0) Быстрая проверка: если уже есть связь — это update ветка, а не create
            $link = OrdersDemands::findOne(['moysklad_order_id' => (string)$order->id]);
            $options = ['sync_positions' => true];

            // если связь есть и не пустой moysklad_demand_id — можно спокойно sync/update
            if ($link) {
                // optional: loop-guard
                if (!empty($link->block_demand_until) && strtotime($link->block_demand_until) > time()) {
                    return;
                }

                // запись уже есть (резерв или реальная)
                $demand = $moysklad->upsertDemandFromOrder(
                    $order,
                    $orderId,
                    $configData,
                    $options
                );

                if ($demand && !empty($demand->id)) {
                    $link->moysklad_demand_id = (string)$demand->id;
                    $link->updated_at = date('Y-m-d H:i:s');
                    $link->save(false);
                } else {
                  file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
                      "DEMAND CREATE FAIL order={$order->id}\n" . print_r($demand,true)."\n",
                      FILE_APPEND
                  );
                }

            }
            else {
                // ⬅️ сюда попадаем ТОЛЬКО если записи реально нет
                $reserve = new OrdersDemands();
                $reserve->order_id = (int)$orderId;
                $reserve->moysklad_order_id = (string)$order->id;
                $reserve->moysklad_demand_id = null;
                $reserve->block_demand_until = date('Y-m-d H:i:s', time() + 30);
                $reserve->created_at = date('Y-m-d H:i:s');
                $reserve->updated_at = date('Y-m-d H:i:s');
                $reserve->save(false);

                $link = $reserve;

                $demand = $moysklad->upsertDemandFromOrder(
                    $order,
                    $orderId,
                    $configData,
                    ['sync_positions' => true]
                );

                if ($demand && !empty($demand->id)) {
                    $link->moysklad_demand_id = (string)$demand->id;
                    $link->updated_at = date('Y-m-d H:i:s');
                    $link->save(false);
                } else {
                  file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
                      "DEMAND CREATE FAIL order={$order->id}\n" . print_r($demand,true)."\n",
                      FILE_APPEND
                  );
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

            // Создаем счет покупателю
            $existsInvoiceOutLocal = OrdersInvoicesOut::find()
                ->where(['moysklad_order_id' => (string)$order->id])
                ->exists();
            if (!$existsInvoiceOutLocal) {
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

            $existsMoneyInLocal = OrdersMoneyin::find()
                ->where(['moysklad_order_id' => (string)$order->id])
                ->exists();

            if(!$existsMoneyInLocal){
              // Узнаем, какой тип платежа требуется
              $paymentAttrId  = Yii::$app->params['moysklad']['paymentTypeAttrId'] ?? null;
              $paymentTypeId  = $paymentAttrId ? $moysklad->getAttributeValueId($order, $paymentAttrId) : null;
              $isCash         = ($paymentTypeId === (Yii::$app->params['moysklad']['cashPaymentTypeId'] ?? ''));
              $docType        = $isCash ? 'cashin' : 'paymentin';

              $row = new OrdersMoneyin();
              $row->order_id           = (int)$orderId;
              $row->moysklad_order_id  = (string)$order->id;
              $row->moysklad_demand_id = (string)$demand->id;
              $row->doc_type           = $docType;
              $row->moysklad_doc_id    = '';
              $row->moysklad_state_id  = '';
              $row->applicable         = 0;
              $row->created_at         = date('Y-m-d H:i:s');
              $row->updated_at         = date('Y-m-d H:i:s');

              try {
                  $row->save(false); // тут должен сработать UNIQUE(demand_id, doc_type)
              } catch (\Throwable $e) {
                  file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
                      "RESERVE FAIL demand={$demand->id} docType={$docType} msg={$e->getMessage()}\n",
                      FILE_APPEND
                  );
              }

              // Создаём документ в МС
              $orderNum = $moysklad->getProductAttribute($order->attributes,'a7f0812d-a0a3-11ed-0a80-114f003fc7f9');
              $orderNum = (!$orderNum) ? '-' : $orderNum->value;

              $paymentType = $moysklad->getProductAttribute($order->attributes,YII::$app->params['moysklad']['paymentTypeAttrId']);
              $paymentTypeMeta = (!$paymentType) ? false : $paymentType->value;

              if(in_array($order->project->id,YII::$app->params['moysklad']['incomeIssues']['marketplaceProjects'])){
                $incomeIssueAttrVal = YII::$app->params['moysklad']['incomeIssues']['marketProdaji'];
              }
              else {
                $incomeIssueAttrVal = YII::$app->params['moysklad']['incomeIssues']['roznProdaji'];
              }

              switch($docType){
                case 'cashin':
                  $incomeIssueAttr = YII::$app->params['moysklad']['incomeIssues']['cashinIssueAttrId'];
                  break;
                default:
                  $incomeIssueAttr = YII::$app->params['moysklad']['incomeIssues']['paymentIssueAttrId'];
              }

              $resDoc = ($docType === 'cashin')
                  ? $moysklad->createCashInFromOrder($order, $demand, $orderNum, $paymentTypeMeta, $incomeIssueAttr, $incomeIssueAttrVal,YII::$app->params['moysklad']['cashInStateWaiting'])
                  : $moysklad->createPaymentInFromOrder($order, $demand, $orderNum, $paymentTypeMeta, $incomeIssueAttr, $incomeIssueAttrVal,YII::$app->params['moysklad']['paymentInStateSchetVistavlen']);

              if (is_array($resDoc) && empty($resDoc['ok'])) {
                  file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
                      strtoupper($docType) . " CREATE FAIL demand={$demand->id} order={$order->id} http={$resDoc['code']} err={$resDoc['err']} resp={$resDoc['raw']}\n",
                      FILE_APPEND
                  );
              }

              $doc   = is_array($resDoc) ? ($resDoc['json'] ?? null) : $resDoc;
              $docId = (string)($doc->id ?? '');

              if ($docId === '') {
                  file_put_contents(__DIR__ . '/../logs/ms_service/updatecustomerorder.txt',
                      strtoupper($docType) . " CREATE FAIL: empty docId demand={$demand->id}\n",
                      FILE_APPEND
                  );
              }

              // 3) Статус "Ожидает поступления" + applicable=false
              if ($docType === 'cashin') {
                  $waiting = Yii::$app->params['moysklad']['cashInStateWaiting'] ?? '';
                  if ($waiting !== '') {
                      $moysklad->updateCashInState($docId, $moysklad->buildStateMeta('cashin', $waiting));
                  }
                  $moysklad->updateCashInApplicable($docId, false);
              } else {
                  $waiting = Yii::$app->params['moysklad']['paymentInStateWaiting'] ?? '';
                  if ($waiting !== '') {
                      $moysklad->updatePaymentInState($docId, $moysklad->buildStateMeta('paymentin', $waiting));
                  }
                  $moysklad->updatePaymentInApplicable($docId, false);
              }

              // 4) Финализируем резерв
              $row->moysklad_doc_id   = $docId;
              $row->moysklad_state_id = $waiting;
              $row->updated_at        = date('Y-m-d H:i:s');
              $row->save(false);

              file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                  "MONEYIN OK demand={$demand->id} docType={$docType} docId={$docId}\n",
                  FILE_APPEND
              );
            }
        }

    }
}
