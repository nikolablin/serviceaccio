<?php
namespace app\services\steps\demands;

use Yii;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2OrdersRepository;
use app\services\repositories\V2DemandsRepository;
use app\models\CashRegisterV2;

class BackToStock extends AbstractStep
{

    protected function isIdempotent(): bool
    {
        return false;
    }

    protected function process(Context $ctx): void
    {
        $demand = $ctx->getDemand();
        if (!$demand || empty($demand->id)) {
            Log::demandUpdate('BackToStock: demand not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }

        $order = $ctx->getOrder();
        if (!$order || empty($order->id)) {
            Log::demandUpdate('BackToStock: order not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }

        $config = $ctx->getConfig();
        if (!$config) {
            Log::demandUpdate('BackToStock: config not resolved', [ 'demandId' => $demand->id ?? null, ]);
            return;
        }

        $patch      = [];

        // Проверяем, нужен ли фискальный чек в отгрузке
        $fiscalVal  = $ctx->ms()->getAttributeValue($demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['fiscal']);
        $needFiscal = ($fiscalVal && $fiscalVal === Yii::$app->params['moyskladv2']['demands']['attributesFieldsValues']['fiscalYes']) ? true : false;

        Log::demandUpdate('BackToStock: fiscal needed', [ 'value' => $needFiscal ]);

        // Фискальный чек требуется, собираем чек
        if($needFiscal):

          $cashRegisterNumber = $config->cash_register;

          if (!$cashRegisterNumber || $cashRegisterNumber === '') {
              Log::demandUpdate('BackToStock: cash register doesnt exist in $config', [ 'demand' => $demand->id , 'config' => $config ]);
              return;
          }

          $cashboxId = CashRegisterV2::cashboxId($cashRegisterNumber);
          $sectionId = CashRegisterV2::sectionId($cashRegisterNumber);

          if (!$cashboxId || !$sectionId) {
              Log::demandUpdate('BackToStock: cash register code or section doesnt exist in $params', [ 'demand' => $demand->id , 'code' => $cashboxId, 'section' => $sectionId ]);
              return;
          }

          $items     = [];
          $totalSum  = 0;

          $paymentTypeId  = $ctx->ms()->getAttributeValue($demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['paymentType']);
          $isCash         = ($paymentTypeId && $paymentTypeId === (Yii::$app->params['moyskladv2']['demands']['attributesFieldsValues']['cashYes'] ?? ''));

          $cashRegisterPaymentType = $isCash ? 0 : 1;

          // Собираем позиции чека
          foreach (($demand->positions->rows ?? []) as $pos) {
            $a = $pos->assortment ?? null;

            $name = (string)($a->name ?? 'Товар');
            $code = (string)($a->code ?? ($a->article ?? ''));
            if ($code === '') $code = 'MS-' . (string)($a->id ?? 'item');

            $qty  = (int)round((float)($pos->quantity ?? 1));
            $unit = (int)round(((int)($pos->price ?? 0)) / 100);

            $ntin = $ctx->ms()->getAttributeValue($a,Yii::$app->params['moyskladv2']['products']['attributesFields']['ntin']);
            $ntin = (!$ntin) ? '-' : $ntin;

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

          // $customerReceipt = [
          //   'name' => $demand->agent->name ?? '',
          //   'phone' => $demand->agent->phone ?? '',
          // ];

          $dataReceipt = [
              'operation'    => Yii::$app->params['ukassa']['operationTypeReturn'],
              'kassa'        => (int)$cashboxId,
              'payments'     => [[
                  'payment_type' => $cashRegisterPaymentType,
                  'total'        => $totalSum,
                  'amount'       => $totalSum,
              ]],
              'items'        => $items,
              'total_amount' => $totalSum,
              'as_html'      => false,
              // 'customer'     => $customerReceipt
          ];

          $receiptId = CashRegisterV2::upsertDraft(
                                          [
                                              'order_ms_id'   => $demand->customerOrder->id ?? null,
                                              'demand_ms_id'  => $demand->id,
                                              'config_id'     => $config->id ?? null,
                                              'cash_register' => $cashRegisterNumber,
                                              'cashbox_id'    => $cashboxId,
                                              'section_id'    => $sectionId,
                                              'operation'     => 'return',
                                              'payment_type'  => $cashRegisterPaymentType,
                                              'total_amount'  => $totalSum,
                                          ],
                                          $dataReceipt
                                    );
          $createReceipt = CashRegisterV2::sendByIdGuarded($receiptId, false);

          if($createReceipt['ok']){
            if(!isset($createReceipt['skipped']) || $createReceipt['skipped'] === false){
              $receiptLink = $createReceipt['json']['data']['link'] ?? null;

              if(!empty($receiptLink)){
                $patch['attributes'][] = [
                  'meta' => $ctx->ms()->buildAttributeMeta('demand',Yii::$app->params['moyskladv2']['demands']['attributesFields']['returnBillLink']),
                  'value' => $receiptLink
                ];
              }
            }
          }
        endif;

        // Обновляем отгрузку
        if (!empty($patch)) {
          $ctx->ms()->request('PUT', "entity/demand/{$demand->id}", $patch);
        }
        $demandStateHref = $demand->state->meta->href ?? null;
        $demandStateId   = $demandStateHref ? basename($demandStateHref) : null;
        (new V2DemandsRepository())->upsert(
            (string)$demand->id,
            (string)$demandStateId,
            (string)$order->id
        );

        // Обновляем заказ
        $ctx->ms()->updateEntityState(
                        'customerorder',
                        $demand->customerOrder->id,
                        $ctx->ms()->buildStateMeta('customerorder',Yii::$app->params['moyskladv2']['orders']['states']['back'])
                      );

        $orderStateId = Yii::$app->params['moyskladv2']['orders']['states']['back'];
        (new V2OrdersRepository())->upsert(
            (string)$order->id,
            (string)$orderStateId
        );
    }
}
