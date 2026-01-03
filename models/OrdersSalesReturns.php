<?php

namespace app\models;

use yii\db\ActiveRecord;
use app\models\Moysklad;
use app\models\Orders;

class OrdersSalesReturns extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%orders_salesreturns}}';
    }

    public static function syncFromMs(object $salesReturn, Moysklad $moysklad): void
    {
        $srId = $salesReturn->id ?? null;
        if (!$srId) return;

        $orderHref = $salesReturn->customerOrder->meta->href ?? null;
        if (!$orderHref) return;

        $msOrderId = basename($orderHref);

        $order = Orders::find()
            ->where(['moysklad_id' => $msOrderId])
            ->one();

        if (!$order) return;

        $stateHref = $salesReturn->state->meta->href ?? null;
        $stateId   = $stateHref ? basename($stateHref) : null;

        $model = self::findOne(['moysklad_salesreturn_id' => $srId]);

        if (!$model) {
            $model = new self();
            $model->created_at = date('Y-m-d H:i:s');
        }

        $model->order_id                = $order->id;
        $model->moysklad_order_id       = $msOrderId;
        $model->moysklad_salesreturn_id = $srId;
        $model->salesreturn_state_id    = $stateId;
        $model->updated_at              = date('Y-m-d H:i:s');

        $model->save(false);
    }
}
