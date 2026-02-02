<?php
namespace app\services\steps\demands;

use Yii;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2DemandsRepository;
use app\services\repositories\V2MoneyInRepository;

class Transferred extends AbstractStep
{
    protected function isIdempotent(): bool
    {
        return false;
    }

    protected function process(Context $ctx): void
    {
        $demand     = $ctx->getDemand();

        $projectId  = $demand->project->meta->href ?? null;
        $projectId  = ($projectId) ? basename($projectId) : null;

        if (!$demand || empty($demand->id)) {
            Log::demandUpdate('Transferred: demand not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }

        $order     = $ctx->getOrder();
        if (!$order || empty($order->id)) {
            Log::demandUpdate('Transferred: order not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }

        // -------------- Входящий платеж ------------------

        $paymentTypeId  = $ctx->ms()->getAttributeValue($demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['paymentType']);
        $isCash         = ($paymentTypeId && $paymentTypeId === (Yii::$app->params['moyskladv2']['demands']['attributesFieldsValues']['cashYes'] ?? ''));
        $moneyType      = ($isCash) ? 'cashin' : 'paymentin';

        Log::demandUpdate("Transferred: money type {$moneyType}");

        $moneyinState   = ($isCash) ?
                              Yii::$app->params['moyskladv2']['moneyin']['states']['cashin']['waitForIncoming']
                              :
                              Yii::$app->params['moyskladv2']['moneyin']['states']['paymentin']['waitForIncoming'];

        $managerAttr          = Yii::$app->params['moyskladv2']['moneyin']['attributesFields'][$moneyType]['manager'];
        $incomeStreamAttr     = Yii::$app->params['moyskladv2']['moneyin']['attributesFields'][$moneyType]['incomeStream'];
        $paymentTypeAttr      = Yii::$app->params['moyskladv2']['moneyin']['attributesFields'][$moneyType]['paymentType'];
        $orderNumberAttr      = Yii::$app->params['moyskladv2']['moneyin']['attributesFields'][$moneyType]['orderNumber'];
        $fiscalAttr           = Yii::$app->params['moyskladv2']['moneyin']['attributesFields'][$moneyType]['fiscalNeed'];

        if(in_array($projectId,Yii::$app->params['moyskladv2']['marketplaceProjects'])){
          $incomeStreamAttrVal = Yii::$app->params['moyskladv2']['moneyin']['attributesFieldsValues']['marketSales'];
        }
        else {
          $incomeStreamAttrVal = Yii::$app->params['moyskladv2']['moneyin']['attributesFieldsValues']['roznSales'];
        }

        $ownerName = '';
        $ownerHref = (string)($demand->owner->meta->href ?? '');
        if ($ownerHref !== '') {
            $employee = $ctx->ms()->getHrefData($ownerHref);
            if ($employee && !empty($employee->name)) {
                $ownerName = (string)$employee->name;
            }
        }

        $fiscalVal  = $ctx->ms()->getAttributeValue($demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['fiscal']);
        $fiscalNeedValue = ($fiscalVal && $fiscalVal === Yii::$app->params['moyskladv2']['demands']['attributesFieldsValues']['fiscalYes']) ? true : false;

        // Проверка входящего платежа
        $paymentExist = false;
        if (!empty($demand->payments) && is_array($demand->payments)) {
            foreach ($demand->payments as $p) {
                $type = $p->meta->type ?? null;
                if ($type === 'paymentin' || $type === 'cashin') {
                    $paymentExist = true;
                    break;
                }
            }
        }

        if (!$paymentExist) {
          $paymentExist = !empty($demand->paymentIn->meta->href) || !empty($demand->cashIn->meta->href);
        }

        if(!$paymentExist){

          $options = [
            'sum'           => $demand->sum,
            'stateId'       => $moneyinState,
            'moment'        => date('Y-m-d H:i:s'),
            'applicable'    => false,
            'incomingDate'  => date('Y-m-d H:i:s'),
            'attributes'    => [
              $managerAttr      => ['type' => 'string', 'value' => $ownerName],
              $incomeStreamAttr => ['type' => 'customentity', 'value' => $incomeStreamAttrVal, 'dictionary' => Yii::$app->params['moyskladv2']['moneyin']['attributesFieldsDictionaries']['incomeStream']],
              $paymentTypeAttr  => ['type' => 'customentity', 'value' => $paymentTypeId, 'dictionary' => Yii::$app->params['moyskladv2']['moneyin']['attributesFieldsDictionaries']['paymentType']],
            ]
          ];

          if(!$isCash):
            $orderNum  = ($ctx->ms()->getAttributeValue( $demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['marketPlaceNum'] ) ?: '');
            $options['attributes'][$orderNumberAttr]  = ['type' => 'string', 'value' => $orderNum];
          else:
            if($fiscalNeedValue){
              $fiscalNeedValue = Yii::$app->params['moyskladv2']['moneyin']['attributesFieldsDictionariesValues']['cashin']['needFiscalYes'];
            }
            else {
              $fiscalNeedValue = Yii::$app->params['moyskladv2']['moneyin']['attributesFieldsDictionariesValues']['cashin']['needFiscalNo'];
            }
            $options['attributes'][$fiscalAttr]       = ['type' => 'customentity', 'value' => $fiscalNeedValue, 'dictionary' => Yii::$app->params['moyskladv2']['moneyin']['attributesFieldsDictionaries']['fiscalNeed']];
          endif;

          $money = $ctx->ms()->ensureMoneyInFromDemand($demand, $moneyType, $options);

          if ($money && !empty($money->data->id)) {
              $money        = $money->data;
              $moneyId      = (string)$money->id;
              $moneyStateId = basename((string)($money->state->meta->href ?? ''));
              $orderMsId    = '';

              if (!empty($demand->customerOrder->meta->href)) {
                  $orderMsId = basename((string)$demand->customerOrder->meta->href);
              } elseif (!empty($ctx->getOrder()->meta->href)) {
                  $orderMsId = basename((string)$ctx->getOrder()->meta->href);
              }

              (new V2MoneyInRepository())->upsert(
                  (string)$money->id,
                  (string)$moneyStateId,
                  (string)$orderMsId,
                  (string)$moneyType,
                  (int)($money->sum ?? 0),
                  json_encode($money, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
              );

          } else {
              Log::demandUpdate( "Transferred: ensureMoneyInFromDemand failed. demand=" . basename((string)($demand->meta->href ?? '')) );
          }

        }

        // Обновить статус отгрузки в локальной бд
        $demandStateHref = $demand->state->meta->href ?? null;
        $demandStateId   = $demandStateHref ? basename($demandStateHref) : null;

        if ($demandStateId) {
            (new V2DemandsRepository())->upsert($demand->id, (string)$demandStateId, $order->id);
            $demand = $ctx->ms()->ensureDemandFromOrder($order, $demand,[ 'state' => Yii::$app->params['moyskladv2']['demands']['states']['todemand'] ]);
        } else {
            Log::demandUpdate('Transferred: demand loaded without state', [ 'orderId'  => $order->id, 'demandId' => $demand->id, ]);
        }

        // Заказу поставить статус Завершен
        $ctx->ms()->updateEntityState(
                        'customerorder',
                        $demand->customerOrder->id,
                        $ctx->ms()->buildStateMeta('customerorder',Yii::$app->params['moyskladv2']['orders']['states']['completed'])
                      );


    }
}
