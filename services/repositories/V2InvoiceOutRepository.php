<?php
namespace app\services\repositories;

use app\models\V2InvoicesOut;

class V2InvoiceOutRepository
{
    public function findByMsId(string $msId): ?V2InvoicesOut
    {
        return V2InvoicesOut::find()->where(['ms_id' => $msId])->one();
    }

    public function findByOrderMsId(string $orderMsId): ?V2InvoicesOut
    {
        return V2InvoicesOut::find()
            ->where(['order_ms_id' => $orderMsId])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    /**
     * Создать/обновить запись по ms_id.
     * updated_at проставится через beforeSave().
     */
    public function upsert(string $msId, string $stateId, ?string $orderMsId = null): V2InvoicesOut
    {
        $model = $this->findByMsId($msId);

        if (!$model) {
            $model = new V2InvoicesOut();
            $model->ms_id = $msId;
        }

        $model->state_id = $stateId;

        // order_ms_id заполняем аккуратно: не перетираем, если уже есть
        if ($orderMsId && !$model->order_ms_id) {
            $model->order_ms_id = $orderMsId;
        }

        $model->save(false);
        return $model;
    }

    /**
     * Удобный метод: обновить только статус (и updated_at)
     */
    public function updateState(string $msId, string $stateId): void
    {
        $model = $this->findByMsId($msId);

        if (!$model) {
            $model = new V2InvoicesOut();
            $model->ms_id = $msId;
        }

        $model->state_id = $stateId;
        $model->save(false);
    }
}
