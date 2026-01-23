<?php
namespace app\services\repositories;

use app\models\V2Orders;

class V2OrdersRepository
{
    public function findByMsId(string $msId): ?V2Orders
    {
        return V2Orders::find()->where(['ms_id' => $msId])->one();
    }

    public function upsert(string $msId, string $stateId): V2Orders
    {
        $model = $this->findByMsId($msId);

        if (!$model) {
            $model = new V2Orders([
                'ms_id'    => $msId,
                'state_id'=> $stateId,
            ]);
        } else {
            $model->state_id = $stateId;
        }

        $model->save(false);
        return $model;
    }
}
