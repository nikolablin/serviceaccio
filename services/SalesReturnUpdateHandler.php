<?php

namespace app\services;

use app\models\Moysklad;
use app\models\Orders;
use app\models\OrdersDemands;

class SalesReturnUpdateHandler
{
    // salesreturn: "Завершен"
    private const SALESRETURN_DONE_STATE_ID = '88b390bc-87dc-11ec-0a80-0fbe0028a739';

    // куда ставим статусы
    private const ORDER_RETURN_STATE_ID  = '02482e52-ee91-11ea-0a80-05f200074472'; // Заказ → Возврат
    private const DEMAND_RETURN_STATE_ID = 'aa7acdbc-a7c9-11ed-0a80-0c71001732ca'; // Отгрузка → Возврат на склад

    private const GUARD_SECONDS = 15;

    public function handle(object $event): void
    {
        if (
            ($event->meta->type ?? null) !== 'salesreturn'
            || ($event->action ?? null) !== 'UPDATE'
        ) {
            return;
        }

        $moysklad = new Moysklad();

        $salesReturnHref = $event->meta->href ?? null;
        if (!$salesReturnHref) return;

        // Берём возврат из МС, т.к. в вебхуке может не быть state/customerOrder
        $sr = $moysklad->getHrefData($salesReturnHref . '?expand=state,customerOrder');

        $srStateHref = $sr->state->meta->href ?? null;
        if (!$srStateHref) return;

        // ✅ обновляем возврат в БД
        OrdersSalesReturns::syncFromMs($sr, $moysklad);

        $srStateId = basename($srStateHref);

        // реагируем только на "Завершен"
        if ($srStateId !== self::SALESRETURN_DONE_STATE_ID) {
            return;
        }

        // связанный заказ
        $orderHref = $sr->customerOrder->meta->href ?? null;
        if (!$orderHref) return;

        $msOrderId = basename($orderHref);

        // локальный заказ
        $orderModel = Orders::find()->where(['moysklad_id' => $msOrderId])->one();
        if (!$orderModel) return;

        // loop-guard (order)
        if (!empty($orderModel->block_order_until) && strtotime($orderModel->block_order_until) > time()) {
            return;
        }

        // ставим блок, чтобы не зациклиться через customerorder UPDATE → demand UPDATE → ...
        $orderModel->block_order_until = date('Y-m-d H:i:s', time() + self::GUARD_SECONDS);
        $orderModel->save(false);

        // 1) Заказ → Возврат
        $moysklad->updateOrderState(
            $msOrderId,
            $moysklad->buildStateMeta('customerorder', self::ORDER_RETURN_STATE_ID)
        );

        // 2) Все отгрузки по заказу → Возврат на склад
        $links = OrdersDemands::find()->where(['moysklad_order_id' => $msOrderId])->all();
        foreach ($links as $link) {
            $msDemandId = $link->moysklad_demand_id ?? null;
            if (!$msDemandId) continue;

            // loop-guard (demand link)
            if (!empty($link->block_demand_until) && strtotime($link->block_demand_until) > time()) {
                continue;
            }

            $link->block_demand_until = date('Y-m-d H:i:s', time() + self::GUARD_SECONDS);
            $link->updated_at = date('Y-m-d H:i:s');
            $link->save(false);

            $moysklad->updateDemandState(
                $msDemandId,
                $moysklad->buildStateMeta('demand', self::DEMAND_RETURN_STATE_ID)
            );
        }
    }
}
