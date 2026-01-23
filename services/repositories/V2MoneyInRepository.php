<?php

namespace app\services\repositories;

use app\models\V2MoneyIn;

class V2MoneyInRepository
{
    public function findByMsId(string $msId): ?V2MoneyIn
    {
        return V2MoneyIn::find()->where(['ms_id' => $msId])->one();
    }

    public function findLastByOrderMsId(string $orderMsId, ?string $type = null): ?V2MoneyIn
    {
        $q = V2MoneyIn::find()->where(['order_ms_id' => $orderMsId]);

        if ($type !== null && $type !== '') {
            $q->andWhere(['type' => $type]);
        }

        return $q->orderBy(['id' => SORT_DESC])->one();
    }

    public function upsert(
        string $msId,
        string $stateId,
        string $orderMsId,
        string $type,
        int $sum = 0,
        ?string $payload = null
    ): V2MoneyIn {
        $model = $this->findByMsId($msId);

        if (!$model) {
            $model = new V2MoneyIn();
            $model->ms_id = $msId;
            $model->type  = $type;
        }

        // order_ms_id не перетираем
        if (empty($model->order_ms_id)) {
            $model->order_ms_id = $orderMsId;
        }

        $model->state_id = $stateId ?: null;
        $model->sum      = (int)$sum;
 
        if ($payload !== null) {
            $model->payload = $payload;
        }

        $model->save(false);
        return $model;
    }

    public function updateState(string $msId, string $stateId): bool
    {
        $model = $this->findByMsId($msId);
        if (!$model) {
            return false;
        }

        $model->state_id = $stateId ?: null;
        return (bool)$model->save(false);
    }
}
