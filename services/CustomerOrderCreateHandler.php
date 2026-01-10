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
            $event->meta->href . '?expand=agent,project,organization,store,state,paymentType,attributes,positions'
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
         * 4) Сохраняем локально
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
         * 5) Применяем конфиг к заказу в МС
         */

       /**
       * Проверяем заказы Каспи. Так как они приходят из системы, то им автоматически назначается статус Подтвержден - К отправке
       * Исключением является недостаточность товара на складе, тогда заказу автоматически присваивается заказ Взять в работу.
       * И автоматически переопрделять его не нужно.
       * Также переопрделять службу доставки не нужно
       */
       if(in_array($projectId,Yii::$app->params['moysklad']['kaspiProjects'])){
         unset($configData->status);
         $configData->delivery_service = $moysklad->getAttributeValueId($order,'8a307d43-3b6a-11ee-0a80-06ae000fd467');
       }

        $updated = $moysklad->updateOrderWithConfig($order->id, $configData);

        if ($updated) {
            $ph = $updated->positions->meta->href ?? null;
            if ($ph) {
                $updated->positions = $moysklad->getHrefData(
                    $ph . '?expand=assortment'
                );
            }
            if (empty($updated->state->meta->href)) {
                $updated = $moysklad->getHrefData(
                    $event->meta->href . '?expand=agent,project,organization,store,state,paymentType,attributes,positions'
                );
            }
            $order = $updated;
        }

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
