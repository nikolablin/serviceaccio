<?php
namespace app\services\steps\orders;

use Yii;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2OrdersRepository;
use app\services\repositories\V2DemandsRepository;

class ApproveToDemand extends AbstractStep
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
            Log::{$log}('ApproveToDemand: order not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }

        $projectId = $order->project->meta->href ?? null;
        $projectId = ($projectId) ? basename($projectId) : null;

        if (!$projectId) {
            Log::{$log}('ApproveToDemand: loaded order without project', [ 'orderId' => $order->id ?? null, ]);
            return;
        }

        // 1) Локальная БД: upsert по ms_id + state_id
        $stateId = $order->state->meta->href ?? null;
        $stateId = ($stateId) ? basename($stateId) : null;

        if (!$stateId) {
            Log::{$log}('ApproveToDemand: loaded order without state', [ 'orderId' => $order->id ?? null, ]);
            return;
        }
        (new V2OrdersRepository())->upsert((string)$order->id, (string)$stateId);


        /* ------------------ Действия при CREATE или UPDATE ---------------- */

        // 2) Резолвим конфиг
        $config = $ctx->getConfig();

        if (!$config) {
            Log::{$log}('ApproveToDemand: config not resolved', [ 'orderId' => $order->id ?? null, ]);
            return;
        }

        $config->status = 'byhand';

        // 3.1) Если проект 🔴 Kaspi, то статус заказа не трогаем и доставка та, которая уже установлена
        if(in_array($projectId,Yii::$app->params['moyskladv2']['kaspiProjects'])){
          $config->status = 'byhand';
          $config->delivery_service = $ctx->ms()->getAttributeValue($order,Yii::$app->params['moyskladv2']['orders']['attributesFields']['delivery']);
        }

        // 3.2) Если проект 🔵 Wolt, то статус заказа не трогаем, и тип платежа берем тот, который установлен в заказе
        if($projectId == Yii::$app->params['moyskladv2']['woltProject']){
          $config->status = 'byhand';
          $config->payment_type   = $ctx->ms()->getAttributeValue($order,Yii::$app->params['moyskladv2']['orders']['attributesFields']['paymentType']);
          $config->payment_status = $ctx->ms()->getAttributeValue($order,Yii::$app->params['moyskladv2']['orders']['attributesFields']['paymentStatus']);
        }

        // 3) Собираем изменения (payload только отличий)
        $diff = $ctx->ms()->buildOrderPatch($order, $config);

        if (empty($diff['payload'])) {
            Log::{$log}('ApproveToDemand: config already applied (no changes)', [ 'orderId' => $order->id ?? null, ]);
        }
        else {
          // 4) Обновляем заказ в МС
          $resp = $ctx->ms()->request('PUT', "entity/customerorder/{$order->id}", $diff['payload']);
          Log::{$log}('ApproveToDemand: MS order updated', [ 'orderId' => $order->id ?? null, 'ok'      => $resp['ok'] ?? false, 'code'    => $resp['code'] ?? null, 'changed' => $diff['changed'] ?? [], ]);
        }


        // 5. Создаем / обновляем отгрузку
        $demand = $ctx->getDemand();

        // Обновляем
        $createDemand = true;
        if ($demand && !empty($demand->id)) {
          $demandMsId = (string)$demand->id;

          $demandStateHref = $demand->state->meta->href ?? null;
          $demandStateId   = $demandStateHref ? basename($demandStateHref) : null;

          if ($demandStateId) {
              (new V2DemandsRepository())->upsert($demandMsId, (string)$demandStateId, $order->id);
              $demand = $ctx->ms()->ensureDemandFromOrder($order, $demand,[ 'state' => Yii::$app->params['moyskladv2']['demands']['states']['todemand'] ]);
          } else {
              Log::{$log}('ApproveToDemand: demand loaded without state', [ 'orderId'  => $order->id, 'demandId' => $demandMsId, ]);
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
                      $demand = $ctx->ms()->ensureDemandFromOrder($order, $demand, [ 'state' => Yii::$app->params['moyskladv2']['demands']['states']['todemand'] ]);
                  }
                  return;
              }

              Log::{$log}('ApproveToDemand: demand href exists but cannot load demand', [ 'orderId'   => $order->id, 'demandHref'=> $demandHref, ]);
              $createDemand = false;
          }
        }

        if($createDemand){ 
          // Вообще не нашли отгрузку, создаем
          $createdDemand = $ctx->ms()->ensureDemandFromOrder($order, null, [ 'state' => Yii::$app->params['moyskladv2']['demands']['states']['todemand'] ]);

          if (!$createdDemand || empty($createdDemand['data']->id)) {
            Log::{$log}('ApproveToDemand: demand create failed', [ 'orderId' => $order->id, 'error' => $createdDemand['raw'] ]);
            return;
          }

          $createdDemand = $createdDemand['data'];

          $createdStateHref = $createdDemand->state->meta->href ?? null;
          $createdStateId   = $createdStateHref ? basename($createdStateHref) : null;

          if ($createdStateId) {
            (new V2DemandsRepository())->upsert((string)$createdDemand->id, (string)$createdStateId, $order->id);
          }

          Log::{$log}('ApproveToDemand: demand created', [ 'orderId'  => $order->id, 'demandId' => (string)$createdDemand->id, ]);
        }
    }
}
