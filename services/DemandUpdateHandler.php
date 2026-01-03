<?php

namespace app\services;

use Yii;
use app\models\Moysklad;
use app\models\Orders;
use app\models\OrdersDemands;
use app\models\OrdersProducts;
use app\models\OrdersMoneyin;

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

        $finalDemandStates = [
          Yii::$app->params['moysklad']['demandStatePassed'] ?? '',
          Yii::$app->params['moysklad']['demandStateClosed'] ?? '',
        ];

        if ($demandStateId && in_array($demandStateId, $finalDemandStates, true)) {
           // сделать 1) деньги 2) статус заказа 3) applicable=false
        }

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
             * ✅ FINAL DEMAND STATES LOGIC
             * Если отгрузка Передан/Закрыт:
             * 1) создать paymentin/cashin со статусом "Ожидает поступления"
             * 2) заказ = "Завершен"
             * 3) applicable=false (снять проводку)
             * + идемпотентность по (demand_id + doc_type)
             * =========================
             */
            $finalDemandStates = [
                Yii::$app->params['moysklad']['demandStatePassed'] ?? '',
                Yii::$app->params['moysklad']['demandStateClosed'] ?? '',
            ];

            if ($demandStateId && in_array($demandStateId, $finalDemandStates, true)) {

                // 1) Грузим заказ из МС (нужны sum, agent, organization, paymentType)
                $msOrder = $moysklad->getHrefData(
                    "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$msOrderId}?expand=agent,organization,paymentType"
                );

                // Определяем тип оплаты (наличные = customentity id)
                $paymentTypeId = null;
                $ptHref = $msOrder->paymentType->meta->href ?? null;
                if ($ptHref) {
                    $paymentTypeId = basename($ptHref);
                }

                $isCash = ($paymentTypeId && $paymentTypeId === (Yii::$app->params['moysklad']['cashPaymentTypeId'] ?? ''));

                $docType = $isCash ? 'cashin' : 'paymentin';

                // Идемпотентность: если уже создавали документ для этой отгрузки — не создаём снова
                $already = OrdersMoneyin::find()
                    ->where([
                        'moysklad_demand_id' => (string)$demand->id,
                        'doc_type' => $docType,
                    ])->exists();

                if (!$already) {

                    if ($isCash) {
                        // Создаём приходный ордер (cashin)
                        $resDoc = $moysklad->createCashInFromOrder($msOrder);
                        if (is_array($resDoc) && empty($resDoc['ok'])) {
                            file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                                "CASHIN CREATE FAIL demand={$demand->id} order={$msOrderId} http={$resDoc['code']} err={$resDoc['err']} resp={$resDoc['raw']}\n",
                                FILE_APPEND
                            );
                        } else {
                            $doc = is_array($resDoc) ? ($resDoc['json'] ?? null) : $resDoc;
                            $docId = (string)($doc->id ?? '');

                            // статус "Ожидает поступления" для cashin
                            $waiting = Yii::$app->params['moysklad']['cashInStateWaiting'] ?? null;
                            if ($docId && $waiting) {
                                $moysklad->updateCashInState($docId, $moysklad->buildStateMeta('cashin', $waiting));
                            }

                            // снять проводку
                            if ($docId) {
                                $moysklad->updateCashInApplicable($docId, false);
                            }

                            // записываем в БД
                            if ($docId) {
                                $row = new OrdersMoneyin();
                                $row->order_id = (int)$orderModel->id;
                                $row->moysklad_order_id = (string)$msOrderId;
                                $row->moysklad_demand_id = (string)$demand->id;
                                $row->doc_type = 'cashin';
                                $row->moysklad_doc_id = $docId;
                                $row->moysklad_state_id = $waiting;
                                $row->applicable = 0;
                                $row->created_at = date('Y-m-d H:i:s');
                                $row->updated_at = date('Y-m-d H:i:s');
                                $row->save(false);
                            }
                        }

                    } else {
                        // Создаём входящий платеж (paymentin)
                        $resDoc = $moysklad->createPaymentInFromOrder($msOrder);
                        if (is_array($resDoc) && empty($resDoc['ok'])) {
                            file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                                "PAYMENTIN CREATE FAIL demand={$demand->id} order={$msOrderId} http={$resDoc['code']} err={$resDoc['err']} resp={$resDoc['raw']}\n",
                                FILE_APPEND
                            );
                        } else {
                            $doc = is_array($resDoc) ? ($resDoc['json'] ?? null) : $resDoc;
                            $docId = (string)($doc->id ?? '');

                            // статус "Ожидает поступления" для paymentin
                            $waiting = Yii::$app->params['moysklad']['paymentInStateWaiting'] ?? null;
                            if ($docId && $waiting) {
                                $moysklad->updatePaymentInState($docId, $moysklad->buildStateMeta('paymentin', $waiting));
                            }

                            // снять проводку
                            if ($docId) {
                                $moysklad->updatePaymentInApplicable($docId, false);
                            }

                            // записываем в БД
                            if ($docId) {
                                $row = new OrdersMoneyin();
                                $row->order_id = (int)$orderModel->id;
                                $row->moysklad_order_id = (string)$msOrderId;
                                $row->moysklad_demand_id = (string)$demand->id;
                                $row->doc_type = 'paymentin';
                                $row->moysklad_doc_id = $docId;
                                $row->moysklad_state_id = $waiting;
                                $row->applicable = 0;
                                $row->created_at = date('Y-m-d H:i:s');
                                $row->updated_at = date('Y-m-d H:i:s');
                                $row->save(false);
                            }
                        }
                    }
                }

                // 2) Статус заказа = Завершен (всегда, даже если документ уже был)
                $completed = Yii::$app->params['moysklad']['orderStateCompleted'] ?? null;
                if ($completed) {
                    $resComplete = $moysklad->updateOrderState(
                        $msOrderId,
                        $moysklad->buildStateMeta('customerorder', $completed)
                    );

                    if (is_array($resComplete) && empty($resComplete['ok'])) {
                        file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                            "ORDER COMPLETE FAIL order={$msOrderId} http={$resComplete['code']} err={$resComplete['err']} resp={$resComplete['raw']}\n",
                            FILE_APPEND
                        );
                    }
                }
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
