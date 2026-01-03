<?php

namespace app\services;

use Yii;
use app\models\Moysklad;
use app\models\Orders;
use app\models\OrdersClients;
use app\models\OrdersProducts;
use app\models\OrdersDemands;
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
         * 4️⃣ Обновляем заказ в МС по конфигу (если нужно)
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
            ->where(['id' => $orderId])
            ->scalar();

        /**
         * 5️⃣ Сохраняем заказ локально
         */
        $orderId = Orders::upsertFromMs($order, $projectId, 1);
        OrdersClients::upsertFromMs($orderId, $order);
        OrdersProducts::syncFromMs($orderId, $order);

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
                $moysklad->upsertDemandFromOrder(
                    $order,
                    $orderId,
                    $configData,
                    [
                        'sync_positions' => true,
                    ]
                );

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

        /**
         * 9️⃣ INVOICEOUT (создать, если заказ = "Счет выставлен" и счета ещё нет)
         *    В params:
         *      - moysklad.orderStateInvoiceIssued (order state id)
         *      - moysklad.invoiceOutStateIssued  (invoiceout state id = "Выставлен")
         */
        $invoiceIssuedState = Yii::$app->params['moysklad']['orderStateInvoiceIssued'] ?? null;
        $invoiceOutState    = Yii::$app->params['moysklad']['invoiceOutStateIssued'] ?? null;

        // лучше реагировать на переход статуса, чтобы не создавать/проверять на каждом UPDATE
        if ($invoiceIssuedState && $stateId === $invoiceIssuedState && $oldStateId !== $stateId) {

            $existsLocal = OrdersInvoicesOut::find()
                ->where(['moysklad_order_id' => (string)$order->id])
                ->exists();

            if (!$existsLocal) {
                // fallback: проверка в МС (если локалка ещё пустая)
                if (!$moysklad->hasInvoiceOutForOrder((string)$order->id)) {

                    $invoice = $moysklad->createInvoiceOutFromOrder($order, $configData);

                    if ($invoice && !is_array($invoice) && !empty($invoice->id)) {

                        // поставить статус "Выставлен" вторым PUT (как ты уже сделал)
                        if ($invoiceOutState) {
                            $moysklad->updateInvoiceOutState(
                                (string)$invoice->id,
                                $moysklad->buildStateMeta('invoiceout', $invoiceOutState)
                            );

                            // опционально подтянуть state, чтобы сохранить корректно
                            if (!empty($invoice->meta->href)) {
                                $invoice = $moysklad->getHrefData($invoice->meta->href . '?expand=state');
                            }
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
}
