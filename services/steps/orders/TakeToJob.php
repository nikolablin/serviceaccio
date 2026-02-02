<?php
namespace app\services\steps\orders;

use Yii;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2OrdersRepository;

class TakeToJob extends AbstractStep
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
          Log::{$log}('TakeToJob: order not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
          return;
      }

      $projectId = $order->project->meta->href ?? null;
      $projectId = ($projectId) ? basename($projectId) : null;

      if (!$projectId) {
          Log::{$log}('TakeToJob: loaded order without project', [ 'orderId' => $order->id ?? null, ]);
          return;
      }

      // 1) Локальная БД: upsert по ms_id + state_id
      $stateId = $order->state->meta->href ?? null;
      $stateId = ($stateId) ? basename($stateId) : null;

      if (!$stateId) {
          Log::{$log}('TakeToJob: loaded order without state', [ 'orderId' => $order->id ?? null, ]);
          return;
      }
      (new V2OrdersRepository())->upsert((string)$order->id, (string)$stateId);


      /* ------------------ Действия при CREATE или UPDATE ---------------- */

      // 2) Резолвим конфиг
      $config = $ctx->getConfig();

      if (!$config) {
          Log::{$log}('TakeToJob: config not resolved', [ 'orderId' => $order->id ?? null, ]);
          return;
      }

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
      $diffOrder = $ctx->ms()->buildOrderPatch($order, $config);

      // 3.1) Проверяем организацию, чтобы, если что накинуть НДС
      $vatPatches      = [];
      $orgId           = $config->organization;
      $orderVatEnabled = false;

      if ($orgId && $ctx->ms()->checkOrganizationVatEnabled($orgId)) {
          $vatPercent = (int)(Yii::$app->params['moyskladv2']['vat']['value'] ?? 16);
          $vatPatches = $ctx->ms()->buildCustomerOrderPositionsVatPatch($order, $vatPercent);
          $orderVatEnabled = true;
      }

      $currentOrderVatEnabled = (bool)($order->vatEnabled ?? false);

      if ($currentOrderVatEnabled !== (bool)$orderVatEnabled) {
          if (empty($diffOrder['payload']) || !is_array($diffOrder['payload'])) {
              $diffOrder['payload'] = [];
          }

          $diffOrder['payload']['vatEnabled'] = (bool)$orderVatEnabled;
          $diffOrder['changed']['vatEnabled'] = [
              'from' => $currentOrderVatEnabled,
              'to'   => (bool)$orderVatEnabled,
          ];
      }

      if (empty($diffOrder['payload'])) {
          Log::{$log}('TakeToJob: config already applied (no changes)', [ 'orderId' => $order->id ?? null, ]);
      }
      else {
        // 4) Обновляем заказ в МС
        $resp = $ctx->ms()->request('PUT', "entity/customerorder/{$order->id}", $diffOrder['payload']);
        Log::{$log}('TakeToJob: MS order updated', [ 'orderId' => $order->id ?? null, 'ok'      => $resp['ok'] ?? false, 'code'    => $resp['code'] ?? null, 'changed' => $diffOrder['changed'] ?? [], ]);
      }


      if (!empty($vatPatches)) {
          // Обновляем позиции, если у них появился/пропал НДС
          $vatApply = $ctx->ms()->applyCustomerOrderPositionsVatPatch((string)$order->id, $vatPatches);

          Log::{$log}('TakeToJob: VAT patch applied', [ 'orderId' => $order->id ?? null, 'result'  => $vatApply ]);
      }


      /* ------------------ Действия Только при UPDATE --------------- */

      if ($ctx->action === 'UPDATE') {

        $demand = $ctx->getDemand();

        // Удаляем документы этого заказа, reset всего заказа
        $deleteEntities = [
            'paymentin'   => 'customerOrder',
            'cashin'      => 'customerOrder',
            'invoiceout'  => 'customerOrder',
            'demand'      => 'customerOrder',
        ];

        // Если у заказа есть отгрузка
        if($demand){
          $demandBill = $ctx->ms()->getAttributeValue($demand,Yii::$app->params['moyskladv2']['demands']['attributesFields']['billLink']);

          // Если у отгрузки есть ссылка на чек, то не удаляем ее, пригодится
          if($demandBill){
            unset($deleteEntities['demand']);
          }
        }

        $del = $ctx->ms()->deleteLinkedDocsForOrder($order,$deleteEntities);
        Log::{$log}('TakeToJob: deleted linked docs', [ 'orderId' => $order->id ?? null, 'result'  => $del, ]);
      }
  }


}
