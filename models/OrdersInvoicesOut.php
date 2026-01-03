<?php

namespace app\models;

use yii\db\ActiveRecord;

class OrdersInvoicesOut extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%orders_invoicesout}}';
    }

    public static function syncFromMsInvoiceOut(object $invoiceOut, int $localOrderId, string $msOrderId): void
    {
        $invId = $invoiceOut->id ?? null;
        if (!$invId) return;

        $stateHref = $invoiceOut->state->meta->href ?? null;
        $stateId   = $stateHref ? basename($stateHref) : null;

        $model = self::findOne(['moysklad_invoiceout_id' => (string)$invId]);

        if (!$model) {
            $model = new self();
            $model->created_at = date('Y-m-d H:i:s');
        }

        $model->order_id              = $localOrderId;
        $model->moysklad_order_id     = (string)$msOrderId;
        $model->moysklad_invoiceout_id = (string)$invId;
        $model->moysklad_state_id     = $stateId;
        $model->updated_at            = date('Y-m-d H:i:s');

        $model->save(false);
    }

}
