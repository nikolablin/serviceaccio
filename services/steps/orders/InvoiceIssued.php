<?php
namespace app\services\steps\orders;

use Yii;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2OrdersRepository;
use app\services\repositories\V2DemandsRepository;
use app\services\repositories\V2InvoiceOutRepository;

class InvoiceIssued extends AbstractStep
{
    protected function isIdempotent(): bool
    {
        return false;
    }

    protected function process(Context $ctx): void
    {

        $order = $ctx->getOrder();
        $log = ($ctx->action === 'UPDATE') ? 'orderUpdate' : 'orderCreate';

        if (!$order || empty($order->id)) {
            Log::{$log}('InvoiceIssued: order not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }

        $projectId = $order->project->meta->href ?? null;
        $projectId = ($projectId) ? basename($projectId) : null;

        if (!$projectId) {
            Log::{$log}('InvoiceIssued: loaded order without project', [ 'orderId' => $order->id ?? null, ]);
            return;
        }

        // 1) Локальная БД: upsert по ms_id + state_id
        $stateId = $order->state->meta->href ?? null;
        $stateId = ($stateId) ? basename($stateId) : null;

        if (!$stateId) {
            Log::{$log}('InvoiceIssued: loaded order without state', [ 'orderId' => $order->id ?? null, ]);
            return;
        }
        (new V2OrdersRepository())->upsert((string)$order->id, (string)$stateId);



        /* ------------------ Действия при CREATE или UPDATE ---------------- */

        // 2) Резолвим конфиг
        $config = $ctx->getConfig();

        // Убираем обновление статуса заказа
        $config->status = 'byhand';

        if (!$config) {
            Log::{$log}('InvoiceIssued: config not resolved', [ 'orderId' => $order->id ?? null, ]);
            return;
        }

        // 3) Собираем изменения (payload только отличий)
        $diff = $ctx->ms()->buildOrderPatch($order, $config);

        $vatPatches      = [];
        $orgId           = $config->organization;
        $orderVatEnabled = false;

        if ($orgId && $ctx->ms()->checkOrganizationVatEnabled($orgId)) {
            $vatPercent = (int)(Yii::$app->params['moyskladv2']['vat']['value'] ?? 16);
            $vatPatches = $ctx->ms()->buildCustomerOrderPositionsVatPatch($order, $vatPercent);
            $orderVatEnabled = true;
        }

        $currentOrderVatEnabled = (bool)($order->vatEnabled ?? false);

        // if ($currentOrderVatEnabled !== (bool)$orderVatEnabled) {
            if (empty($diff['payload']) || !is_array($diff['payload'])) {
                $diff['payload'] = [];
            }

            $diff['payload']['vatEnabled'] = (bool)$orderVatEnabled;
            $diff['changed']['vatEnabled'] = [
                'from' => $currentOrderVatEnabled,
                'to'   => (bool)$orderVatEnabled,
            ];

            Log::{$log}("InvoiceIssued: vat enabled");
        // }

        $resetOrder = false;

        if (empty($diff['payload'])) {
            Log::{$log}('InvoiceIssued: config already applied (no changes)', [ 'orderId' => $order->id ?? null, ]);
        }
        else {
          $resp = $ctx->ms()->request('PUT', "entity/customerorder/{$order->id}", $diff['payload']);
          $resetOrder = true;
          Log::{$log}('InvoiceIssued: MS order updated', [ 'orderId' => $order->id ?? null, 'ok' => $resp['ok'] ?? false, 'code'    => $resp['code'] ?? null, 'changed' => $diff['changed'] ?? [], ]);
        }

        if (!empty($vatPatches)) {
          $vatApply = $ctx->ms()->applyCustomerOrderPositionsVatPatch((string)$order->id, $vatPatches);
          $resetOrder = true;
          Log::{$log}('InvoiceIssued: VAT patch applied', [ 'orderId' => $order->id ?? null, 'result'  => $vatApply ]);
        }


        if($resetOrder){
          $order = $ctx->ms()->getCustomerOrder((string)$order->id);
        }

        /* ------------------ Действия при CREATE или UPDATE ---------------- */
        /* При данном статусе должны быть созданы:
            - Отгрузка (Счет выставлен),
            - Счет на оплату (Счет выставлен, поля - Организация, Счет, Склад, Контрагент),
            - Входящий платеж или приходный ордер (Счет выставлен, поля - Способ оплаты, Статья доходов, Ответственный, Номер заказа маркетплейса)
        */

        // -------------- Счет на оплату ------------------

        $invoiceOut = $ctx->getInvoice();

        // Если не найден в контексте
        if (!$invoiceOut) {
            Log::{$log}('InvoiceIssued: invoiceout not found on context', [ 'orderId' => $order->id ?? null ]);

            $invHref = null;
            if (!empty($order->invoicesOut) && is_array($order->invoicesOut)) {
                $invHref = $order->invoicesOut[0]->meta->href ?? null;
            }

            // Если есть ссылка в заказе
            if ($invHref) {
                $invId = basename($invHref);
                $invoiceOut = $ctx->ms()->getInvoiceOut($invId);
            }
            else {
              Log::{$log}('InvoiceIssued: invoiceout not found on order', [ 'orderId' => $order->id ?? null ]);
            }
        }

        // Если не нашли, создаем
        if (!$invoiceOut) {
            $createdInvoiceout = $ctx->ms()->ensureInvoiceOutFromOrder($order, null, [ 'state' => Yii::$app->params['moyskladv2']['invoicesout']['states']['invoiceissued'] ?? null ]);

            if (!$createdInvoiceout || empty($createdInvoiceout['data']->id)) {
                Log::{$log}('InvoiceIssued: invoiceout create failed', [ 'orderId' => $order->id, 'raw'     => $createdInvoiceout['raw'] ?? null ]);
            } else {
                $invoiceOut = $createdInvoiceout['data'];
            }
        }
        // Если нашли, то обновляем
        else {
            $invoiceOut = $ctx->ms()->ensureInvoiceOutFromOrder($order, $invoiceOut, [ 'state' => Yii::$app->params['moyskladv2']['invoicesout']['states']['invoiceissued'] ?? null ]);
        }

        // Обновляем в локальной базе
        if ($invoiceOut && !empty($invoiceOut->id)) {
            $invStateHref = $invoiceOut->state->meta->href ?? null;
            $invStateId = $invStateHref ? basename($invStateHref) : null;

            if ($invStateId) {
                (new V2InvoiceOutRepository())->upsert((string)$invoiceOut->id, (string)$invStateId, (string)$order->id);
            } else {
                Log::{$log}('InvoiceIssued: invoiceout loaded without state', [ 'orderId'   => $order->id, 'invoiceId' => $invoiceOut->id ?? null, ]);
            }
        }


        // -------------- Отгрузка ------------------

        $demand = $ctx->getDemand();

        $createDemand = true;
        if ($demand && !empty($demand->id)) {
          $demandMsId = (string)$demand->id;

          $demandStateHref = $demand->state->meta->href ?? null;
          $demandStateId   = $demandStateHref ? basename($demandStateHref) : null;

          if ($demandStateId) {
              (new V2DemandsRepository())->upsert($demandMsId, (string)$demandStateId, $order->id);
              $demand = $ctx->ms()->ensureDemandFromOrder($order, $demand,[ 'state' => Yii::$app->params['moyskladv2']['demands']['states']['invoiceissued'] ]);
          } else {
              Log::{$log}('InvoiceIssued: demand loaded without state', [ 'orderId'  => $order->id, 'demandId' => $demandMsId, ]);
          }

          $createDemand = false;
        }
        // Не нашли в getDemand, ищем прямо в заказе
        else {
          $demandHref = null;
          if (!empty($order->demands) && is_array($order->demands)) {
              $demandHref = $order->demands[0]->meta->href ?? null;
          }

          if ($demandHref) {
              // Context не загрузил, но ссылка есть — загрузим явно
              $demandMsId = basename($demandHref);
              $demand = $ctx->ms()->getDemand($demandMsId);

              if ($demand && !empty($demand->id)) {
                  $demandStateHref = $demand->state->meta->href ?? null;
                  $demandStateId   = $demandStateHref ? basename($demandStateHref) : null;

                  if ($demandStateId) {
                      (new V2DemandsRepository())->upsert((string)$demand->id, (string)$demandStateId, $order->id);
                      $demand = $ctx->ms()->ensureDemandFromOrder($order, $demand, [ 'state' => Yii::$app->params['moyskladv2']['demands']['states']['invoiceissued'] ]);
                  }
                  return;
              }

              Log::{$log}('InvoiceIssued: demand href exists but cannot load demand', [ 'orderId'   => $order->id, 'demandHref'=> $demandHref, ]);
              $createDemand = false;
          }
        }

        if($createDemand){
          // Вообще не нашли отгрузку, создаем
          $createdDemand = $ctx->ms()->ensureDemandFromOrder($order, null, [ 'state' => Yii::$app->params['moyskladv2']['demands']['states']['invoiceissued'] ]);

          if (!$createdDemand || empty($createdDemand['data']->id)) {
            Log::{$log}('InvoiceIssued: demand create failed', [ 'orderId' => $order->id, 'error' => $createdDemand['raw'] ]);
            return;
          }

          $createdDemand = $createdDemand['data'];

          $createdStateHref = $createdDemand->state->meta->href ?? null;
          $createdStateId   = $createdStateHref ? basename($createdStateHref) : null;

          if ($createdStateId) {
            (new V2DemandsRepository())->upsert((string)$createdDemand->id, (string)$createdStateId, $order->id);
          }

          $demand = $createdDemand;

          Log::{$log}('InvoiceIssued: demand created', [ 'orderId'  => $order->id, 'demandId' => (string)$createdDemand->id, ]);
        }

        // -------------- Входящий платеж ------------------

        if ($demand) {
          $paymentTypeId  = $ctx->ms()->getAttributeValue($demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['paymentType']);
          $isCash         = ($paymentTypeId && $paymentTypeId === (Yii::$app->params['moyskladv2']['demands']['attributesFieldsValues']['cashYes'] ?? ''));
          $moneyType      = ($isCash) ? 'cashin' : 'paymentin';
          $moneyinState   = ($isCash) ?
                                Yii::$app->params['moyskladv2']['moneyin']['states']['cashin']['waitForIncoming']
                                :
                                Yii::$app->params['moyskladv2']['moneyin']['states']['paymentin']['invoiceissued'];

          $managerAttr          = Yii::$app->params['moyskladv2']['moneyin']['attributesFields'][$moneyType]['manager'];
          $incomeStreamAttr     = Yii::$app->params['moyskladv2']['moneyin']['attributesFields'][$moneyType]['incomeStream'];
          $paymentTypeAttr      = Yii::$app->params['moyskladv2']['moneyin']['attributesFields'][$moneyType]['paymentType'];
          $orderNumberAttr      = Yii::$app->params['moyskladv2']['moneyin']['attributesFields'][$moneyType]['orderNumber'];

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

          // Создаем Входящий платеж, только если оплата Безналичными
          if(!$isCash):

            $orderNum  = ($ctx->ms()->getAttributeValue( $demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['marketPlaceNum'] ) ?: '');
            $options['attributes'][$orderNumberAttr] = ['type' => 'string', 'value' => $orderNum];

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

                (new \app\services\repositories\V2MoneyInRepository())->upsert(
                    (string)$money->id,
                    (string)$moneyStateId,
                    (string)$orderMsId,
                    (string)$moneyType,
                    (int)($money->sum ?? 0),
                    json_encode($money, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }
            else {
                Log::orderUpdate( "InvoiceIssued: ensureMoneyInFromDemand failed. demand=" . basename((string)($demand->meta->href ?? '')) );
            }

          endif;

        }


        // -------------- Счет-фактура ------------------

        if($demand) {
          $options = [
            'stateId'     => Yii::$app->params['moyskladv2']['factureOut']['states']['created'],
            'applicable'  => true,
            'moment'      => date('Y-m-d H:i:s'),
            'created'     => date('Y-m-d H:i:s'),
          ];
          $factureout = $ctx->ms()->ensureFactureoutFromDemand($demand, $options);
        }
    }
}
