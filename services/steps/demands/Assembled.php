<?php
namespace app\services\steps\demands;

use Yii;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2DemandsRepository;
use app\models\CashRegisterV2;
use app\models\Kaspi;
use app\services\Wolt;
use app\services\WoltOrderImporter;

class Assembled extends AbstractStep
{
    protected function isIdempotent(): bool
    {
        return false;
    }

    protected function process(Context $ctx): void
    {
        $kaspi        = new Kaspi();
        $wolt         = new Wolt();
        $woltimporter = new WoltOrderImporter();

        $demand     = $ctx->getDemand();
        $projectId  = $demand->project->meta->href ?? null;
        $projectId  = ($projectId) ? basename($projectId) : null;

        $patch      = [];
        $filesMeta  = [];

        if (!$demand || empty($demand->id)) {
            Log::demandUpdate('Assembled: demand not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }

        $config = $ctx->getConfig();

        if (!$config) {
            Log::demandUpdate('Assembled: config not resolved', [ 'demandId' => $demand->id ?? null, ]);
            return;
        }

        // Проверяем, нужен ли фискальный чек в отгрузке
        $fiscalVal  = $ctx->ms()->getAttributeValue($demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['fiscal']);
        $needFiscal = ($fiscalVal && $fiscalVal === Yii::$app->params['moyskladv2']['demands']['attributesFieldsValues']['fiscalYes']) ? true : false;

        Log::demandUpdate('Assembled: fiscal needed', [ 'value' => $needFiscal ]);

        $createReceipt = false;

        // Фискальный чек требуется, собираем чек
        if($needFiscal):

          $cashRegisterNumber = $config->cash_register;

          if (!$cashRegisterNumber || $cashRegisterNumber === '') {
              Log::demandUpdate('Assembled: cash register doesnt exist in $config', [ 'demand' => $demand->id , 'config' => $config ]);
              return;
          }

          $cashboxId = CashRegisterV2::cashboxId($cashRegisterNumber);
          $sectionId = CashRegisterV2::sectionId($cashRegisterNumber);

          if (!$cashboxId || !$sectionId) {
              Log::demandUpdate('Assembled: cash register code or section doesnt exist in $params', [ 'demand' => $demand->id , 'code' => $cashboxId, 'section' => $sectionId ]);
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
          //   'name' => $demand->agent->name,
          //   'phone' => $demand->agent->phone
          // ];

          // Данные чека
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
              // 'customer'     => $customerReceipt
          ];

          // Собираем Draft в локальную БД
          $receiptId = CashRegisterV2::upsertDraft(
                                          [
                                              'order_ms_id'   => $demand->customerOrder->id ?? null,
                                              'demand_ms_id'  => $demand->id,
                                              'config_id'     => $config->id ?? null,
                                              'cash_register' => $cashRegisterNumber,
                                              'cashbox_id'    => $cashboxId,
                                              'section_id'    => $sectionId,
                                              'operation'     => 'sell',
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
                  'meta' => $ctx->ms()->buildAttributeMeta('demand',Yii::$app->params['moyskladv2']['demands']['attributesFields']['billLink']),
                  'value' => $receiptLink
                ];
              }
            }
          }
        endif;

        // Если проект 🔴 Kaspi, нужно получить накладную
        if(in_array($projectId,Yii::$app->params['moyskladv2']['kaspiProjects'])){
          $waybillMark      = $ctx->ms()->getAttributeValue( $demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['waybillMark'] );

          if(!$waybillMark){
            $kaspiOrderNum  = ($ctx->ms()->getAttributeValue( $demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['marketPlaceNum'] ) ?: false);
            $placesNum      = (int)($ctx->ms()->getAttributeValue( $demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['numPlaces'] ) ?: 1);
            if ($placesNum <= 0) $placesNum = 1;

            if($kaspiOrderNum){
              $waybill = $kaspi->setKaspiReadyForDelivery($kaspiOrderNum,$placesNum,'readyForDeliveryV2',$projectId);

              if (is_string($waybill) && $waybill !== '') {
                $fileMeta = $ctx->ms()->ensureFileFromUrl($waybill, 'demand', $demand->id, 'Накладная Kaspi_' . $kaspiOrderNum . '.pdf');
                if ($fileMeta) {
                    $patch['attributes'][] = [
                      'meta' => $ctx->ms()->buildAttributeMeta('demand',Yii::$app->params['moyskladv2']['demands']['attributesFields']['waybillMark']),
                      'value' => true
                    ];
                }
              }
            }
          }
        }

        // Если проект 🔵 Wolt, то нужно отправить метку о принятии заказа
        if($projectId == Yii::$app->params['moyskladv2']['woltProject']){
          $woltOrderNum  = (string)($ctx->ms()->getAttributeValue( $demand, Yii::$app->params['moyskladv2']['demands']['attributesFields']['marketPlaceNum'] ) ?: '-');

          if ($woltOrderNum) { 
              $venueId = $woltimporter->getVenueIdByOrderId($woltOrderNum);

              if ($venueId) {
                  $resp = $wolt->markOrderReady($woltOrderNum, $venueId);
              } else {
                Log::demandUpdate('Assembled: Wolt Order Venue ID error', [ 'value' => $venueId ]);
              }
          }
        }

        // Если есть обновления по отгрузке, то обновляем ее
        if (!empty($patch)) {
          $ctx->ms()->request('PUT', "entity/demand/{$demand->id}", $patch);
        }

        // Заказу поставить статус Собран
        $ctx->ms()->updateEntityState(
                        'customerorder',
                        $demand->customerOrder->id,
                        $ctx->ms()->buildStateMeta('customerorder',Yii::$app->params['moyskladv2']['orders']['states']['assembled'])
                      );
    }
}
