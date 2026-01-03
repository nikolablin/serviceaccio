<?php

namespace app\services;

use Yii;

class MoyskladSyncConfig
{
    public static function allowDemandStates(): array
    {
        return Yii::$app->params['moysklad']['allowDemandStates'] ?? [];
    }

    public static function orderToDemandStateMap(): array
    {
        return Yii::$app->params['moysklad']['stateMapOrderToDemand'] ?? [];
    }

    public static function demandToOrderStateMap(): array
    {
        return Yii::$app->params['moysklad']['stateMapDemandToOrder'] ?? [];
    }

    public static function loopTTL(): int
    {
        return (int)(Yii::$app->params['moysklad']['loopGuardTtl'] ?? 10);
    }
}
