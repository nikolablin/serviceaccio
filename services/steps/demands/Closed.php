<?php
namespace app\services\steps\demands;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2DemandsRepository;

class Closed extends AbstractStep
{
    protected function process(Context $ctx): void
    {
      // Не используется
    }
}
