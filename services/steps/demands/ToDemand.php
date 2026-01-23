<?php
namespace app\services\steps\demands;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2DemandsRepository;

class ToDemand extends AbstractStep
{
    protected function process(Context $ctx): void
    {
        $msDemandId = $this->extractEntityId($ctx);
        if (!$msDemandId) {
            return;
        }

        // логика статуса "К отгрузке"
        // - возможно создать чек при переходе?
        // - обновить локальные поля
        // - и т.д.
    }
}
