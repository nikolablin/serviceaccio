<?php
namespace app\services\steps\demands;

use Yii;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2DemandsRepository;
use app\models\CashRegisterV2;

class InvoiceIssued extends AbstractStep
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
            Log::demandUpdate('InvoiceIssued: demand not loaded', [ 'href' => $ctx->event->meta->href ?? null, ]);
            return;
        }

        $config = $ctx->getConfig();

        if (!$config) {
            Log::demandUpdate('InvoiceIssued: config not resolved', [ 'demandId' => $demand->id ?? null, ]);
            return;
        }



    }
}
