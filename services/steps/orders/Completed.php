<?php
namespace app\services\steps\orders;

use app\services\steps\AbstractStep;
use app\services\support\Context;
use app\services\support\Log;
use app\services\repositories\V2OrderRepository;

class Completed extends AbstractStep
{
    protected function process(Context $ctx): void
    {
        // 1) вытащить entityId (id заказа в МС)
        $msOrderId = $this->extractEntityId($ctx);
        if (!$msOrderId) {
            return;
        }

        // 2) загрузить/синхронизировать локальный заказ (если нужно)
        // $ctx->localOrderId = ...

        // 3) загрузить config (OrdersConfigResolver) (если нужно)
        // $ctx->config = ...

        // 4) бизнес-логика статуса "Собран"
        // - создать/обновить отгрузку
        // - обновить статусы
        // - инициировать чек/платеж и т.п.
    }
}
