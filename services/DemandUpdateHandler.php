<?php

namespace app\services;

use Yii;
use app\models\Moysklad;
use app\models\Orders;
use app\models\OrdersDemands;
use app\models\OrdersProducts;
use app\models\OrdersMoneyin;
use app\models\CashRegister;
use app\models\OrdersReceipts;
use app\models\OrdersConfigTable;
use app\models\OrdersSalesReturns;
use app\models\Kaspi;

class DemandUpdateHandler
{
    private function resolveCashRegisterCodeForOrder(object $order): string
    {
        // project id из MS-заказа (uuid)
        $projectId = (string)($order->project->id ?? '');

        if ($projectId === '') {
            return '';
        }

        $cfg = OrdersConfigTable::find()
            ->select(['cash_register'])
            ->where(['project' => $projectId])
            ->asArray()
            ->one();

        $code = (string)($cfg['cash_register'] ?? '');
        return trim($code);
    }

    public function handle(object $event): void
    {
        if ( ($event->meta->type ?? null) !== 'demand' || ($event->action ?? null) !== 'UPDATE' ) {
          return;
        }

        $moysklad = new Moysklad();
        $kaspi = new Kaspi();

        /**
         * 1️⃣ Загружаем отгрузку из МС (state + positions)
         */
        $demand = $moysklad->getHrefData(
            $event->meta->href . '?expand=state,positions,attributes,project'
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

        $STATE_DEMAND_DO_RETURN       = $cfg['stateDemandDoReturn'] ?? '';

        $STATE_ORDER_RETURN_FINAL     = $cfg['stateOrderReturn'];

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

        $msOrderCache = [];
        $msOrderInvoicesCache = [];

        foreach ($links as $link) {

            $msOrderId = $link->moysklad_order_id ?? null;
            if (!$msOrderId) {
                continue;
            }

            if (!isset($msOrderCache[$msOrderId])) {
                $msOrderCache[$msOrderId] = $moysklad->getHrefData(
                    "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$msOrderId}?expand=project,agent,organization,paymentType,attributes"
                );
            }
            $msOrder = $msOrderCache[$msOrderId];

            if (empty($msOrder->id)) {
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


            file_put_contents( __DIR__ . '/../logs/ms_service/updatedemand.txt', 'TEST1:::' . print_r($demandStateId,true) . PHP_EOL, FILE_APPEND );
            file_put_contents( __DIR__ . '/../logs/ms_service/updatedemand.txt', 'TEST2:::' . print_r($STATE_DEMAND_COLLECTED,true) . PHP_EOL, FILE_APPEND );


            // Ветка: Отгрузка “Собран”
            if ($demandStateId === $STATE_DEMAND_COLLECTED) {

                // 2) Если "Фискальный чек" == Да → выбить чек
                $fiscalVal = $moysklad->getAttributeValueId($demand, $ATTR_FISCAL_CHECK);
                $needFiscal = ($fiscalVal === $ATTR_FISCAL_CHECK_YES);

                file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                    "COLLECTED demand={$demand->id} order={$msOrderId} fiscalVal=" . ($fiscalVal ?? 'NULL') . " needFiscal=" . ($needFiscal ? '1':'0') . "\n",
                    FILE_APPEND
                );

                file_put_contents( __DIR__ . '/../logs/ms_service/updatedemand.txt', 'TEST3:::' . print_r($needFiscal,true) . PHP_EOL, FILE_APPEND );

                if ($needFiscal) {

                    $cashRegisterCode = $this->resolveCashRegisterCodeForOrder($msOrder);

                    if ($cashRegisterCode === '') {
                        file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                            "FISCAL SKIP: cash_register empty (no config) demand={$demand->id}\n",
                            FILE_APPEND
                        );
                        continue;
                    }

                    $receiptType = 'sale';

                    // 1) собрать payload (как у тебя)
                    $items    = [];
                    $totalSum = 0;

                    $cashboxId = CashRegister::getCashboxIdByRegister($cashRegisterCode);
                    $sectionId = CashRegister::getSectionIdByRegister($cashRegisterCode);

                    file_put_contents( __DIR__ . '/../logs/ms_service/updatedemand.txt', 'TEST4:::' . print_r($cashboxId,true) . PHP_EOL, FILE_APPEND );
                    file_put_contents( __DIR__ . '/../logs/ms_service/updatedemand.txt', 'TEST5:::' . print_r($sectionId,true) . PHP_EOL, FILE_APPEND );

                    $paymentAttrId            = Yii::$app->params['moysklad']['demandPaymentTypeAttrId'] ?? null;

                    file_put_contents( __DIR__ . '/../logs/ms_service/updatedemand.txt', 'TEST6:::' . print_r($paymentAttrId,true) . PHP_EOL, FILE_APPEND );

                    $paymentTypeId            = $paymentAttrId ? $moysklad->getAttributeValueId($demand, $paymentAttrId) : null;

                    file_put_contents( __DIR__ . '/../logs/ms_service/updatedemand.txt', 'PAYMENT_DATA:::' . print_r($paymentTypeId,true) . PHP_EOL, FILE_APPEND );

                    $isCash = ($paymentTypeId === (Yii::$app->params['moysklad']['cashPaymentTypeId'] ?? ''));
                    $cashRegisterPaymentType = $isCash ? 0 : 1;

                    file_put_contents( __DIR__ . '/../logs/ms_service/updatedemand.txt', 'PAYMENT_DATA:::' . print_r($cashRegisterPaymentType,true) . PHP_EOL, FILE_APPEND );

                    foreach (($demand->positions->rows ?? []) as $pos) {
                        $a = $pos->assortment ?? null;

                        $name = (string)($a->name ?? 'Товар');
                        $code = (string)($a->code ?? ($a->article ?? ''));
                        if ($code === '') $code = 'MS-' . (string)($a->id ?? 'item');

                        $qty  = (int)round((float)($pos->quantity ?? 1));
                        $unit = (int)round(((int)($pos->price ?? 0)) / 100);

                        $ntin = $moysklad->getProductAttribute($a->attributes,'594f2460-e4af-11f0-0a80-192e0037459c');
                        $ntin = (!$ntin) ? '-' : $ntin->value;

                        $lineTotal = $qty * $unit;
                        $totalSum += $lineTotal;

                        $items[] = [
                            'name'         => $name,
                            'price'        => $unit,
                            'total_amount' => $lineTotal,
                            'quantity'     => $qty,
                            'is_nds'       => true,
                            'ntin'         => $ntin,
                            'section'      => $sectionId,
                        ];
                    }

                    $dataReceipt = [
                        'operation'    => Yii::$app->params['ukassa']['operationTypeSell'],
                        'kassa'        => (int)$cashboxId,
                        'payments'     => [[
                            'payment_type' => $cashRegisterPaymentType,
                            'total'        => $totalSum,
                            'amount'       => $totalSum,
                        ]],
                        'items'        => $items,
                        'total_amount' => $totalSum,
                        'as_html'      => false,
                    ];

                    // 2) получить/создать черновик (атомарно) и обновить payload
                    $metaReceipt = [
                        'order_id'           => (int)($orderModel->id ?? 0),
                        'moysklad_order_id'  => (string)($msOrder->id ?? ''),
                        'moysklad_demand_id' => (string)($demand->id ?? ''),
                        'receipt_type'       => $receiptType,
                    ];

                    $receiptId = CashRegister::upsertReceiptDraft($cashRegisterCode, $metaReceipt, $dataReceipt);

                    // 3) отправка с защитой: не слать, если уже есть ticket или sent
                    $sent = CashRegister::sendReceiptByIdGuarded($receiptId, false);

                    file_put_contents(__DIR__ . '/../logs/ms_service/ukassa_receipt.txt',
                        "SEND receipt_id={$receiptId}\nRESULT=" . print_r($sent, true) . "\n----\n",
                        FILE_APPEND
                    );

                    $receiptLink = $sent['json']['data']['link'] ?? null;
                    if ($receiptLink) {
                        $attrs = $moysklad->buildDemandAttributePayload('1ff6c2e8-1c3a-11ec-0a80-06650003408f', $receiptLink);
                        $moysklad->updateDemandAttributes((string)$demand->id, $attrs);
                    }
                }

                // Если заказ Каспи, то нужно получить накладные и добавить их
                if (in_array($msOrder->project->id, Yii::$app->params['moysklad']['kaspiProjects'], true)) {
                  $kaspiOrderNum = $moysklad->getProductAttribute($msOrder->attributes,'a7f0812d-a0a3-11ed-0a80-114f003fc7f9');
                  $kaspiOrderNum = (!$kaspiOrderNum) ? '-' : $kaspiOrderNum->value;

                  $placesNum = $moysklad->getProductAttribute($demand->attributes,'f1d4a71a-c29a-11eb-0a80-001f0003a1be');
                  $placesNum = (!$placesNum) ? 1 : $placesNum->value;

                  $kaspi->setKaspiReadyForDelivery($kaspiOrderNum,$placesNum,'readyForDelivery',$msOrder->project->id);
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


            // Ветка: “🚫 БЕЗ ЧЕКА - Возврат на склад”
            if ($demandStateId === $STATE_DEMAND_RETURN_NO_CHECK) {

                file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                    "RETURN_NO_CHECK demand={$demand->id} order={$msOrderId}\n",
                    FILE_APPEND
                );

                // 2) Снять проводку с отгрузки
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
                if (!isset($msOrderInvoicesCache[$msOrderId])) {
                    $msOrderInvoicesCache[$msOrderId] = $moysklad->getHrefData(
                        "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$msOrderId}?expand=invoicesOut"
                    );
                }
                $msOrderFull = $msOrderInvoicesCache[$msOrderId];

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


            // Ветка: “Провести возврат”
            if ($demandStateId === $STATE_DEMAND_DO_RETURN) {

                file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                    "DO_RETURN demand={$demand->id} order={$msOrderId}\n",
                    FILE_APPEND
                );

                /**
                 * 0) needFiscal — включаем и для возврата
                 *    (берём по отгрузке, как в ветке COLLECTED)
                 */
                $fiscalVal  = $moysklad->getAttributeValueId($demand, $ATTR_FISCAL_CHECK);
                $needFiscal = ($fiscalVal === $ATTR_FISCAL_CHECK_YES);

                file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                    "DO_RETURN fiscalVal=" . ($fiscalVal ?? 'NULL') . " needFiscal=" . ($needFiscal ? '1' : '0') . "\n",
                    FILE_APPEND
                );

                /**
                 * 1) Идемпотентность salesreturn по нашей БД
                 */
                $existingReturn = OrdersSalesReturns::find()
                    ->where([
                        'moysklad_order_id'  => (string)$msOrderId,
                        'moysklad_demand_id' => (string)$demand->id,
                    ])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();

                $srId = (string)($existingReturn->moysklad_salesreturn_id ?? '');

                if ($srId !== '') {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "DO_RETURN SKIP (already exists) salesreturn={$srId}\n",
                        FILE_APPEND
                    );
                } else {

                    // 2) Создаём salesreturn в МС
                    $resSr = $moysklad->createSalesReturnFromDemand($msOrder, $demand);

                    if (is_array($resSr) && empty($resSr['ok'])) {
                        file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                            "SALESRETURN CREATE FAIL demand={$demand->id} order={$msOrderId} http={$resSr['code']} err={$resSr['err']} resp={$resSr['raw']}\n",
                            FILE_APPEND
                        );
                        continue;
                    }

                    $sr   = is_array($resSr) ? ($resSr['json'] ?? null) : $resSr;
                    $srId = (string)($sr->id ?? '');

                    if ($srId === '') {
                        file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                            "SALESRETURN CREATE FAIL: empty id demand={$demand->id}\n",
                            FILE_APPEND
                        );
                        continue;
                    }

                    // 3) Пишем/обновляем OrdersSalesReturns
                    $row = $existingReturn ?: new OrdersSalesReturns();
                    $row->order_id                = (int)$orderModel->id;
                    $row->moysklad_order_id       = (string)$msOrderId;
                    $row->moysklad_demand_id      = (string)$demand->id;
                    $row->moysklad_salesreturn_id = (string)$srId;
                    $row->moysklad_state_id       = (string)$demandStateId;

                    // лучше хранить именно ID статуса, а не href
                    $srStateHref = $sr->state->meta->href ?? null;
                    $row->salesreturn_state_id = $srStateHref ? basename($srStateHref) : null;

                    $row->created_at = $row->created_at ?: date('Y-m-d H:i:s');
                    $row->updated_at = date('Y-m-d H:i:s');
                    $row->save(false);

                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "SALESRETURN OK id={$srId} row_id={$row->id}\n",
                        FILE_APPEND
                    );
                }

                /**
                 * 4) Чек возврата — ТОЛЬКО если needFiscal
                 */
                if ($needFiscal) {

                    $cashRegisterCode = $this->resolveCashRegisterCodeForOrder($msOrder);

                    if ($cashRegisterCode === '') {
                        file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                            "RETURN RECEIPT SKIP: cash_register empty demand={$demand->id}\n",
                            FILE_APPEND
                        );
                    } else {

                        // собрать payload
                        $items = [];
                        $totalSum = 0;

                        $cashboxId    = CashRegister::getCashboxIdByRegister($cashRegisterCode);
                        $sectionId    = CashRegister::getSectionIdByRegister($cashRegisterCode);

                        $paymentAttrId            = Yii::$app->params['moysklad']['demandPaymentTypeAttrId'] ?? null;
                        $paymentTypeId            = $paymentAttrId ? $moysklad->getAttributeValueId($demand, $paymentAttrId) : null;
                        $cashRegisterPaymentType  = ($paymentTypeId === (Yii::$app->params['moysklad']['cashPaymentTypeId'] ? 0 : 1));

                        foreach (($demand->positions->rows ?? []) as $pos) {
                            $a = $pos->assortment ?? null;

                            $name = (string)($a->name ?? 'Товар');
                            $code = (string)($a->code ?? ($a->article ?? ''));
                            if ($code === '') $code = 'MS-' . (string)($a->id ?? 'item');

                            $qty  = (int)round((float)($pos->quantity ?? 1));
                            $unit = (int)round(((int)($pos->price ?? 0)) / 100);

                            $ntin = $moysklad->getProductAttribute($a->attributes,'594f2460-e4af-11f0-0a80-192e0037459c');
                            $ntin = (!$ntin) ? '-' : $ntin->value;

                            $lineTotal = $qty * $unit;
                            $totalSum += $lineTotal;

                            $items[] = [
                                'name'         => $name,
                                'price'        => $unit,
                                'total_amount' => $lineTotal,
                                'quantity'     => $qty,
                                'is_nds'       => true,
                                'ntin'         => $ntin,
                                'section'      => $sectionId,
                            ];
                        }

                        $dataReceipt = [
                            'operation'    => Yii::$app->params['ukassa']['operationTypeReturn'], // sell_return
                            'kassa'        => (int)$cashboxId,
                            'payments'     => [[
                                'payment_type' => $cashRegisterPaymentType,
                                'total'        => $totalSum,
                                'amount'       => $totalSum,
                            ]],
                            'items'        => $items,
                            'total_amount' => $totalSum,
                            'as_html'      => false,
                        ];

                        $metaReceipt = [
                            'order_id'           => (int)($orderModel->id ?? 0),
                            'moysklad_order_id'  => (string)($msOrder->id ?? ''),
                            'moysklad_demand_id' => (string)($demand->id ?? ''),
                            'receipt_type'       => 'return',
                        ];

                        // ⚠️ важно: только через upsert + guarded send
                        $receiptId = CashRegister::upsertReceiptDraft($cashRegisterCode, $metaReceipt, $dataReceipt);
                        $sent      = CashRegister::sendReceiptByIdGuarded((int)$receiptId, false);

                        file_put_contents(__DIR__ . '/../logs/ms_service/ukassa_receipt.txt',
                            "SEND RETURN receipt_id={$receiptId}\nRESULT=" . print_r($sent, true) . "\n----\n",
                            FILE_APPEND
                        );

                        $receiptLink = $sent['json']['data']['link'] ?? null;

                        if ($receiptLink) {
                            // атрибут ссылки возвратного чека
                            $attrs = $moysklad->buildDemandAttributePayload('2362f797-d068-11ec-0a80-0b8a00a44340', $receiptLink);
                            $moysklad->updateDemandAttributes((string)$demand->id, $attrs);
                        }
                    }
                } else {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "RETURN RECEIPT SKIP: needFiscal=0 demand={$demand->id}\n",
                        FILE_APPEND
                    );
                }

                /**
                 * 5) Заказу ставим статус Возврат
                 */
                $resState = $moysklad->updateOrderState(
                    $msOrderId,
                    $moysklad->buildStateMeta('customerorder', $STATE_ORDER_RETURN_FINAL)
                );

                if (is_array($resState) && empty($resState['ok'])) {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "ORDER SET RETURN FAIL order={$msOrderId} http={$resState['code']} err={$resState['err']} resp={$resState['raw']}\n",
                        FILE_APPEND
                    );
                }

                continue;
            }



            // Ветка "ЗАвершен"/"Закрыт"
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

                // Определяем тип оплаты (наличные = customentity id)
                $paymentAttrId  = Yii::$app->params['moysklad']['paymentTypeAttrId'] ?? null;
                $paymentTypeId  = $paymentAttrId ? $moysklad->getAttributeValueId($msOrder, $paymentAttrId) : null;
                $isCash         = ($paymentTypeId === (Yii::$app->params['moysklad']['cashPaymentTypeId'] ?? ''));
                $docType        = $isCash ? 'cashin' : 'paymentin';

                /**
                 * =========================
                 * 🔐 ИДЕМПОТЕНТНОСТЬ (HARD)
                 * reserve-before-POST
                 * =========================
                 */

                file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                    "FINAL BRANCH demand={$demand->id} order={$msOrderId} docType={$docType}\n",
                    FILE_APPEND
                );

                // 1) Резервируем запись ДО запроса в МС
                $row = new OrdersMoneyin();
                $row->order_id           = (int)$orderModel->id;
                $row->moysklad_order_id  = (string)$msOrderId;
                $row->moysklad_demand_id = (string)$demand->id;
                $row->doc_type           = $docType;

                // ВАЖНО: чаще всего эти поля NOT NULL в БД → ставим пустые строки
                $row->moysklad_doc_id    = '';
                $row->moysklad_state_id  = '';
                $row->applicable         = 0;
                $row->created_at         = date('Y-m-d H:i:s');
                $row->updated_at         = date('Y-m-d H:i:s');

                try {
                    $row->save(false); // тут должен сработать UNIQUE(demand_id, doc_type)
                } catch (\Throwable $e) {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        "RESERVE FAIL demand={$demand->id} docType={$docType} msg={$e->getMessage()}\n",
                        FILE_APPEND
                    );
                    continue;
                }

                file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                    "RESERVE OK id={$row->id}\n",
                    FILE_APPEND
                );

                // 2) Создаём документ в МС
                $resDoc = ($docType === 'cashin')
                    ? $moysklad->createCashInFromOrder($msOrder, $demand)
                    : $moysklad->createPaymentInFromOrder($msOrder, $demand);

                if (is_array($resDoc) && empty($resDoc['ok'])) {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        strtoupper($docType) . " CREATE FAIL demand={$demand->id} order={$msOrderId} http={$resDoc['code']} err={$resDoc['err']} resp={$resDoc['raw']}\n",
                        FILE_APPEND
                    );
                    continue;
                }

                $doc   = is_array($resDoc) ? ($resDoc['json'] ?? null) : $resDoc;
                $docId = (string)($doc->id ?? '');

                if ($docId === '') {
                    file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt',
                        strtoupper($docType) . " CREATE FAIL: empty docId demand={$demand->id}\n",
                        FILE_APPEND
                    );
                    continue;
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
