<?php
namespace app\services\steps\factureout;

use Yii;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;

class Created extends AbstractStep
{
    protected function isIdempotent(): bool
    {
        return false;
    }

    protected function process(Context $ctx): void
    {
        $factureout = $ctx->getFactureout();
        if (!$factureout || empty($factureout->id)) {
            Log::factureoutUpdate('Created: factureout not loaded');
            return;
        }

        $demand = $ctx->getDemandFromFactureout($factureout);
        if (!$demand || empty($demand->id)) {
          Log::factureoutUpdate('Created: demand not loaded');
          return;
        }

        $moment = new \DateTime();
        $moment = $moment->modify('-2 hours');
        $options = [
          'moment'      => $moment->format('Y-m-d H:i:s'),
          'created'     => $moment->format('Y-m-d H:i:s'),
        ];

        try {
            $factureout = $ctx->ms()->ensureFactureoutFromDemand($demand, $options);
            Log::factureoutUpdate('Created: factureout PUT success', [ 'factureoutId' => $factureout->id ]);
        } catch (\Throwable $e) {
            Log::factureoutUpdate('Created: factureout PUT failed', [ 'factureoutId' => $factureout->id, 'error' => $e->getMessage(), ]);
        }

    }
}
