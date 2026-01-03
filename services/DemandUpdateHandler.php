<?php

namespace app\services;

use Yii;
use app\models\Moysklad;
use app\models\Orders;
use app\models\OrdersDemands;
use app\models\OrdersProducts;

class DemandUpdateHandler
{
    public function handle(object $event): void
    {
        if (
            ($event->meta->type ?? null) !== 'demand'
            || ($event->action ?? null) !== 'UPDATE'
        ) {
            return;
        }

        $moysklad = new Moysklad();

        /**
         * 1️⃣ Загружаем отгрузку из МС (state + positions)
         */
        $demand = $moysklad->getHrefData(
            $event->meta->href . '?expand=state,positions'
        );

        if (empty($demand->id)) {
            return;
        }

        // позиции отгрузки
        $positionsHref = $demand->positions->meta->href ?? null;
        if ($positionsHref) {
            $demand->positions = $moysklad->getHrefData(
                $positionsHref . '?expand=assortment'
            );
        }

        /**
         * 2️⃣ Определяем статус отгрузки
         */
        $demandStateHref = $demand->state->meta->href ?? null;
        $demandStateId   = $demandStateHref ? basename($demandStateHref) : null;

        /**
         * 3️⃣ Находим связанные заказы локально
         */
        $links = OrdersDemands::find()
            ->where(['moysklad_demand_id' => (string)$demand->id])
            ->all();

        if (!$links) {
            return;
        }

        /**
         * 4️⃣ Маппинг DEMAND → ORDER (статусы)
         */
        $stateMap = Yii::$app->params['moysklad']['stateMapDemandToOrder'] ?? [];

        foreach ($links as $link) {

            $msOrderId = $link->moysklad_order_id ?? null;
            if (!$msOrderId) {
                continue;
            }

            $orderModel = Orders::find()
                ->where(['moysklad_id' => (string)$msOrderId])
                ->one();

            if (!$orderModel) {
                continue;
            }

            /**
             * =========================
             * 5️⃣ LOOP-GUARD (order)
             * =========================
             */
            if (
                !empty($orderModel->block_order_until)
                && strtotime($orderModel->block_order_until) > time()
            ) {
                continue;
            }

            /**
             * =========================
             * 6️⃣ СИНХРОНИЗАЦИЯ ПОЗИЦИЙ
             *     DEMAND → ORDER
             * =========================
             */
            if (!empty($demand->positions->rows)) {

                // Перезаписываем позиции заказа ЛОКАЛЬНО
                OrdersProducts::syncFromMsDemand(
                    $orderModel->id,
                    $demand
                );

                // Ставим loop-guard
                $orderModel->block_order_until = date(
                    'Y-m-d H:i:s',
                    time() + (int)(Yii::$app->params['moysklad']['loopGuardTtl'] ?? 10)
                );
                $orderModel->save(false);

                $resPos = $moysklad->updateOrderPositionsFromDemand($msOrderId, $demand);
                if (is_array($resPos) && empty($resPos['ok'])) {
                    // логируй, иначе тихо не поймёшь почему не применилось
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "ORDER POS FAIL order={$msOrderId} http={$resPos['code']} err={$resPos['err']} resp={$resPos['raw']}\n",
                        FILE_APPEND
                    );
                }
            }

            /**
             * =========================
             * 7️⃣ СИНК СТАТУСА ЗАКАЗА
             *     DEMAND → ORDER
             * =========================
             */
            if ($demandStateId && isset($stateMap[$demandStateId])) {

                $orderStateId   = $stateMap[$demandStateId];
                $orderStateMeta = $moysklad->buildStateMeta(
                    'customerorder',
                    $orderStateId
                );

                $res = $moysklad->updateOrderState(
                    $msOrderId,
                    $orderStateMeta
                );

                if (is_array($res) && empty($res['ok'])) {
                    continue;
                }
            }

            /**
             * =========================
             * 8️⃣ Обновляем связь
             * =========================
             */
            $link->updated_at = date('Y-m-d H:i:s');
            $link->save(false);
        }
    }
}
