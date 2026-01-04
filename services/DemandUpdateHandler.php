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
        if ( ($event->meta->type ?? null) !== 'demand' || ($event->action ?? null) !== 'UPDATE' ) {
          return;
        }

        $moysklad = new Moysklad();


        /**
         * 1️⃣ Загружаем отгрузку из МС (state + positions)
         */
        $demand = $moysklad->getHrefData(
            $event->meta->href . '?expand=state,positions,attributes'
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

        $cfg = Yii::$app->params['moysklad']['demandUpdateHandler'] ?? [];

        $STATE_DEMAND_COLLECTED       = $cfg['stateDemandCollected'] ?? '';
        $STATE_DEMAND_RETURN_NO_CHECK = $cfg['stateDemandReturnNoCheck'] ?? '';

        $ATTR_FISCAL_CHECK            = $cfg['attrFiscalCheck'] ?? '';
        $ATTR_FISCAL_CHECK_YES        = $cfg['attrFiscalCheckYes'] ?? '';

        $STATE_ORDER_COLLECTED        = $cfg['stateOrderCollected'] ?? '';
        $STATE_ORDER_RETURN           = $cfg['stateOrderReturn'] ?? '';

        $STATE_INVOICE_CANCELED       = $cfg['stateInvoiceCanceled'] ?? '';

        $STATE_PAYMENTIN_CANCELED     = $cfg['statePaymentInCanceled'] ?? '';
        $STATE_CASHIN_CANCELED        = $cfg['stateCashInCanceled'] ?? '';

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

            // 1) Всегда фиксируем статус отгрузки в локалке
            $link->moysklad_state_id = (string)$demandStateId;
            $link->updated_at = date('Y-m-d H:i:s');
            $link->save(false);


            // Ветка A: Отгрузка “Собран”
            if ($demandStateId === $STATE_DEMAND_COLLECTED) {

                // 2) Если "Фискальный чек" == Да → выбить чек
                $fiscalVal = $moysklad->getAttributeValueId($demand, $ATTR_FISCAL_CHECK);
                $needFiscal = ($fiscalVal === $ATTR_FISCAL_CHECK_YES);

                file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                    "COLLECTED demand={$demand->id} order={$msOrderId} fiscalVal=" . ($fiscalVal ?? 'NULL') . " needFiscal=" . ($needFiscal ? '1':'0') . "\n",
                    FILE_APPEND
                );

                if ($needFiscal) {
                    /**
                     * ⚠️ Тут нужен твой метод "выбить чек".
                     * Я не вижу его в коде, поэтому предлагаю интерфейс:
                     * - либо $moysklad->createFiscalCheckFromDemand($demand)
                     * - либо $moysklad->createFiscalCheckFromOrderId($msOrderId)
                     *
                     * Подставь реальный метод/интеграцию (касса/ОФД).
                     */
                     // TODO: чек
                     // $resCheck = $moysklad->createFiscalCheckFromDemand($demand); // <-- замени на реальный вызов

                    // if (is_array($resCheck) && empty($resCheck['ok'])) {
                    //     file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                    //         "FISCAL CHECK FAIL demand={$demand->id} order={$msOrderId} http={$resCheck['code']} err={$resCheck['err']} resp={$resCheck['raw']}\n",
                    //         FILE_APPEND
                    //     );
                    // }
                }

                // 3) Заказу поставить статус "Собран"
                $res = $moysklad->updateOrderState(
                    $msOrderId,
                    $moysklad->buildStateMeta('customerorder', $STATE_ORDER_COLLECTED)
                );

                if (is_array($res) && empty($res['ok'])) {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "ORDER SET COLLECTED FAIL order={$msOrderId} http={$res['code']} err={$res['err']} resp={$res['raw']}\n",
                        FILE_APPEND
                    );
                }

                // Чтобы дальше код не перетёр статус маппингом/позициями
                continue;
            }


            // Ветка B: “🚫 БЕЗ ЧЕКА - Возврат на склад”
            if ($demandStateId === $STATE_DEMAND_RETURN_NO_CHECK) {

                file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                    "RETURN_NO_CHECK demand={$demand->id} order={$msOrderId}\n",
                    FILE_APPEND
                );

                // 2) Снять проводку с отгрузки
                // Нужен метод в Moysklad (аналог updatePaymentInApplicable/updateCashInApplicable)
                $resAppDemand = $moysklad->updateDemandApplicable((string)$demand->id, false); // <-- добавь/используй существующий
                if (is_array($resAppDemand) && empty($resAppDemand['ok'])) {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "DEMAND APPLICABLE OFF FAIL demand={$demand->id} http={$resAppDemand['code']} err={$resAppDemand['err']} resp={$resAppDemand['raw']}\n",
                        FILE_APPEND
                    );
                }

                // 3) Заказу статус "Возврат"
                $resState = $moysklad->updateOrderState(
                    $msOrderId,
                    $moysklad->buildStateMeta('customerorder', $STATE_ORDER_RETURN)
                );
                if (is_array($resState) && empty($resState['ok'])) {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "ORDER SET RETURN FAIL order={$msOrderId} http={$resState['code']} err={$resState['err']} resp={$resState['raw']}\n",
                        FILE_APPEND
                    );
                }

                // 4) Снять проводку с заказа
                $resAppOrder = $moysklad->updateOrderApplicable($msOrderId, false); // <-- добавь/используй существующий
                if (is_array($resAppOrder) && empty($resAppOrder['ok'])) {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "ORDER APPLICABLE OFF FAIL order={$msOrderId} http={$resAppOrder['code']} err={$resAppOrder['err']} resp={$resAppOrder['raw']}\n",
                        FILE_APPEND
                    );
                }

                /**
                 * 5-6) Счет покупателя (customerinvoice / invoiceout) — найти и аннулировать + applicable=false
                 * Способ 1 (желательно): ищем счет через customerorder expand=invoicesOut
                 */
                $msOrderFull = $moysklad->getHrefData(
                    "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$msOrderId}?expand=invoicesOut"
                );

                $invoices = $msOrderFull->invoicesOut->rows ?? [];
                foreach ($invoices as $inv) {
                    $invId = $inv->id ?? null;
                    if (!$invId) continue;

                    $resInvState = $moysklad->updateInvoiceOutState($invId, $moysklad->buildStateMeta('invoiceout', $STATE_INVOICE_CANCELED)); // <-- добавить метод
                    if (is_array($resInvState) && empty($resInvState['ok'])) {
                        file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                            "INVOICE STATE FAIL invoice={$invId} order={$msOrderId} http={$resInvState['code']} err={$resInvState['err']} resp={$resInvState['raw']}\n",
                            FILE_APPEND
                        );
                    }

                    $resInvApp = $moysklad->updateInvoiceOutApplicable($invId, false); // <-- добавить метод
                    if (is_array($resInvApp) && empty($resInvApp['ok'])) {
                        file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                            "INVOICE APPLICABLE OFF FAIL invoice={$invId} order={$msOrderId} http={$resInvApp['code']} err={$resInvApp['err']} resp={$resInvApp['raw']}\n",
                            FILE_APPEND
                        );
                    }
                }

                /**
                 * 7) Входящий платеж / приходный ордер — отменить
                 * Берём по нашей локальной таблице orders_moneyin, т.к. ты её уже ведёшь
                 */
                $money = OrdersMoneyin::find()
                    ->where(['moysklad_demand_id' => (string)$demand->id])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();

                if ($money && !empty($money->moysklad_doc_id)) {
                    $docId = (string)$money->moysklad_doc_id;

                    if ($money->doc_type === 'paymentin') {
                        $moysklad->updatePaymentInState($docId, $moysklad->buildStateMeta('paymentin', $STATE_PAYMENTIN_CANCELED));
                        $moysklad->updatePaymentInApplicable($docId, false);
                        $money->moysklad_state_id = $STATE_PAYMENTIN_CANCELED;
                        $money->applicable = 0;
                        $money->updated_at = date('Y-m-d H:i:s');
                        $money->save(false);
                    } elseif ($money->doc_type === 'cashin') {
                        $moysklad->updateCashInState($docId, $moysklad->buildStateMeta('cashin', $STATE_CASHIN_CANCELED));
                        $moysklad->updateCashInApplicable($docId, false);
                        $money->moysklad_state_id = $STATE_CASHIN_CANCELED;
                        $money->applicable = 0;
                        $money->updated_at = date('Y-m-d H:i:s');
                        $money->save(false);
                    }
                } else {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "MONEYIN NOT FOUND demand={$demand->id} order={$msOrderId}\n",
                        FILE_APPEND
                    );
                }

                // Чтобы дальше не синкались позиции и не мапился статус поверх возврата
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

            if ($demandStateId && in_array($demandStateId, $finalDemandStates, true)) {

                // 1) Грузим заказ из МС (нужны sum, agent, organization, paymentType)
                $msOrder = $moysklad->getHrefData(
                    "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$msOrderId}?expand=agent,organization,paymentType,attributes"
                );

                // Определяем тип оплаты (наличные = customentity id)
                $paymentAttrId  = Yii::$app->params['moysklad']['paymentTypeAttrId'] ?? null;
                $paymentTypeId  = $paymentAttrId ? $moysklad->getAttributeValueId($msOrder, $paymentAttrId) : null;

                $isCash = ($paymentTypeId === (Yii::$app->params['moysklad']['cashPaymentTypeId'] ?? ''));

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
