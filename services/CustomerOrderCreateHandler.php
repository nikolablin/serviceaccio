<?php

namespace app\services;

use Yii;
use app\models\Moysklad;
use app\models\Orders;
use app\models\OrdersClients;
use app\models\OrdersProducts;
use app\models\OrdersConfigTable;
use app\models\OrdersInvoicesOut;

class CustomerOrderCreateHandler
{
    public function handle(object $event): void
    {
        if (
            ($event->meta->type ?? null) !== 'customerorder'
            || ($event->action ?? null) !== 'CREATE'
        ) {
            return;
        }

        $moysklad = new Moysklad();

        /**
         * 1️⃣ Загружаем заказ из МС
         */
        $order = $moysklad->getHrefData(
            $event->meta->href . '?expand=agent,project,organization,store,state,paymentType,attributes'
        );

        if (empty($order->project->meta->href)) {
            return;
        }

        $projectId = basename($order->project->meta->href);

        /**
         * 2️⃣ Работаем ТОЛЬКО с проектами,
         *     для которых есть конфигурация
         */ 
         $configData = (new \app\services\OrdersConfigResolver())->resolve($order);
        // $configData = OrdersConfigTable::findOne([
        //     'project' => $projectId,
        // ]);

        if (!$configData) {
            // Проект не обслуживается — просто игнорируем
            return;
        }

        /**
         * 3️⃣ Загружаем позиции заказа
         */
        $positionsHref = $order->positions->meta->href ?? null;
        if ($positionsHref) {
            $order->positions = $moysklad->getHrefData(
                $positionsHref . '?expand=assortment'
            );
        }

        /**
         * 4️⃣ Применяем конфиг к заказу в МС
         */
        $updated = $moysklad->updateOrderWithConfig($order->id, $configData);

        // file_put_contents(__DIR__ . '/../logs/ms_service/test.txt',print_r($updated,true));
        // exit();

        if ($updated) {
            $ph = $updated->positions->meta->href ?? null;
            if ($ph) {
                $updated->positions = $moysklad->getHrefData(
                    $ph . '?expand=assortment'
                );
            }
            if (empty($updated->state->meta->href)) {
                $updated = $moysklad->getHrefData(
                    $event->meta->href . '?expand=agent,project,organization,store,state,paymentType,attributes'
                );
            }
            $order = $updated;
        }

        /**
         * 5️⃣ Сохраняем локально
         */
        $stateHref = $order->state->meta->href ?? null;
        $stateId   = $stateHref ? basename($stateHref) : null;

        $orderId = Orders::upsertFromMs($order, $projectId, 1);

        // ✅ текущий статус в локалку
        if ($stateId) {
            Orders::updateAll(
                ['moysklad_state_id' => $stateId, 'updated_at' => date('Y-m-d H:i:s')],
                ['id' => $orderId]
            );
        }

        OrdersClients::upsertFromMs($orderId, $order);
        OrdersProducts::syncFromMs($orderId, $order);

        /**
         * 6️⃣ Создание отгрузки (ТОЛЬКО нужные статусы)
         */
        $allowStates = Yii::$app->params['moysklad']['allowDemandStates'] ?? [];

        if ($stateId && in_array($stateId, $allowStates, true)) {
            $moysklad->upsertDemandFromOrder(
                $order,
                $orderId,
                $configData
            );
        }

        /**
         * 7️⃣ Счет покупателю (invoiceout)
         *     — только если статус = "Счет выставлен"
         *     — и если счета ещё нет
         */

         $invoiceIssuedState = Yii::$app->params['moysklad']['orderStateInvoiceIssued'] ?? null;
         $invoiceOutState    = Yii::$app->params['moysklad']['invoiceOutStateIssued'] ?? null;

         if ($invoiceIssuedState && $stateId === $invoiceIssuedState) {

           $existsLocal = OrdersInvoicesOut::find()
               ->where(['moysklad_order_id' => (string)$order->id])
               ->exists();

           if (!$existsLocal) {
               // fallback: check in MS (на случай, если таблица ещё не заполнялась)
               if (!$moysklad->hasInvoiceOutForOrder((string)$order->id)) {
                   $invoice = $moysklad->createInvoiceOutFromOrder($order, $configData);

                   // записываем связь
                   if ($invoice && !is_array($invoice)) {
                     if ($invoice && !is_array($invoice)) {
                         // ✅ поставить статус "Выставлен"
                         if ($invoiceOutState) {
                             $moysklad->updateInvoiceOutState(
                                 (string)$invoice->id,
                                 $moysklad->buildStateMeta('invoiceout', $invoiceOutState)
                             );

                             // (опционально) перезагрузить invoiceout, чтобы записать актуальный state_id
                             $invoice = $moysklad->getHrefData(
                                 $invoice->meta->href . '?expand=state'
                             );
                         }

                         OrdersInvoicesOut::syncFromMsInvoiceOut($invoice, $orderId, (string)$order->id);
                     }
                   }
               }
           }
       }
    }
}
