<?php
namespace app\services\steps\salesreturn;

use Yii;
use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2DemandsRepository;

class Finish extends AbstractStep
{
    protected function isIdempotent(): bool
    {
        return false;
    }

    protected function process(Context $ctx): void
    {
        $salesreturnId = $this->extractEntityId($ctx);
        if (!$salesreturnId) {
            return;
        }

        $salesreturn = $ctx->getSalesreturn();

        $demand = $ctx->getDemandFromSalesreturn($salesreturn);
        if (!$demand || empty($demand->id)) {
            Log::salesreturnUpdate('Finish: demand not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
        }

        $ctx->ms()->updateEntityState(
                        'demand',
                        $demand->id,
                        $ctx->ms()->buildStateMeta('customerorder',Yii::$app->params['moyskladv2']['demands']['states']['closed'])
                      );

        $ctx->ms()->updateEntityState(
                        'customerorder',
                        $demand->customerOrder->id,
                        $ctx->ms()->buildStateMeta('customerorder',Yii::$app->params['moyskladv2']['orders']['states']['completed'])
                      );
    }
}
