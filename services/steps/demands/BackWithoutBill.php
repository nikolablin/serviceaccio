<?php
namespace app\services\steps\demands;

use Yii;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2OrdersRepository;
use app\services\repositories\V2DemandsRepository;
use app\services\repositories\V2MoneyInRepository;

class BackWithoutBill extends AbstractStep
{
    protected function isIdempotent(): bool
    {
        return false;
    }

    protected function process(Context $ctx): void
    {
        $demand = $ctx->getDemand();
        if (!$demand || empty($demand->id)) {
            Log::demandUpdate('BackWithoutBill: demand not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }
 
        $order = $ctx->getOrder();
        if (!$order || empty($order->id)) {
            Log::demandUpdate('BackWithoutBill: order not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }

        $orderPatch     = [];
        $invoicePatch   = [];
        $moneyinPatch   = [];
        $demandPatch    = [];

        // Отключаем проводку отгрузки
        $demandPatch['applicable'] = false;

        // Отключаем проводку заказа
        $orderPatch['applicable'] = false;
        $orderPatch['state'] = ['meta' => $ctx->ms()->buildStateMeta('customerorder',Yii::$app->params['moyskladv2']['orders']['states']['back'])];

        // Отключаем проводку счета на оплату
        $invoiceOut = $ctx->getInvoice();

        if($invoiceOut && !empty($invoiceOut->id)){
          $invoicePatch['applicable'] = false;
        }

        // Меняем статус платежа на Отмененный и отключаем проводку
        $paymentTypeId  = $ctx->ms()->getAttributeValue($demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['paymentType']);
        $isCash         = ($paymentTypeId && $paymentTypeId === (Yii::$app->params['moyskladv2']['demands']['attributesFieldsValues']['cashYes'] ?? ''));

        $moneyIn = false;
        if($isCash){
          $moneyType = 'cashin';
          $moneyIn = $ctx->getCashIn();
        }
        else {
          $moneyType = 'paymentin';
          $moneyIn = $ctx->getPaymentIn();
        }

        $moneyState = Yii::$app->params['moyskladv2']['moneyin']['states'][$moneyType]['cancelled'];

        if($moneyIn && !empty($moneyIn->id)){
          $moneyinPatch['applicable'] = false;
          $moneyinPatch['state'] = ['meta' => $ctx->ms()->buildStateMeta($moneyType,$moneyState)];
        }

        // Обновляем сущности

        // Отгрузка
        try {
            $ctx->ms()->request('PUT', "entity/demand/{$demand->id}", $demandPatch);

            $demandStateHref = $demand->state->meta->href ?? null;
            $demandStateId   = $demandStateHref ? basename($demandStateHref) : null;

            (new V2DemandsRepository())->upsert(
                (string)$demand->id,
                (string)$demandStateId,
                (string)$order->id
            );

        } catch (\Throwable $e) {
            Log::demandUpdate('BackWithoutBill: demand PUT failed', [ 'demandId' => $demand->id, 'error'    => $e->getMessage(), ]);
        }


        // Заказ
        try {
            $ctx->ms()->request('PUT', "entity/customerorder/{$order->id}", $orderPatch);

            $orderStateId = Yii::$app->params['moyskladv2']['orders']['states']['back'];
            (new V2OrdersRepository())->upsert(
                (string)$order->id,
                (string)$orderStateId
            );

        } catch (\Throwable $e) {
            Log::demandUpdate('BackWithoutBill: order PUT failed', [ 'orderId' => $order->id, 'error'   => $e->getMessage(), ]);
        }

        // Счет
        if (!empty($invoicePatch) && $invoiceOut && !empty($invoiceOut->id)) {
            try {
                $ctx->ms()->request('PUT', "entity/invoiceout/{$invoiceOut->id}", $invoicePatch);
            } catch (\Throwable $e) {
                Log::demandUpdate('BackWithoutBill: invoice PUT failed', [ 'invoiceId' => $invoiceOut->id, 'error'     => $e->getMessage(), ]);
            }
        }

        // Входящий платеж
        if (!empty($moneyinPatch) && $moneyIn && !empty($moneyIn->id)) {
            try {
                $ctx->ms()->request('PUT', "entity/{$moneyType}/{$moneyIn->id}", $moneyinPatch);

                (new V2MoneyInRepository())->upsert(
                    (string)$moneyIn->id,
                    (string)$moneyState,
                    (string)$order->id,
                    (string)$moneyType,
                    (int)($moneyIn->sum ?? 0),
                    json_encode($moneyIn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );

            } catch (\Throwable $e) {
                Log::demandUpdate('BackWithoutBill: moneyin PUT failed', [ 'moneyType' => $moneyType, 'moneyId'   => $moneyIn->id, 'error'     => $e->getMessage() ]);
            }
        }

    }
}
