<?php
namespace app\models;

use yii\db\ActiveRecord;

class KaspiOrders extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%kaspi_orders}}';
    }

    public function rules()
    {
        return [
            [['order_id', 'extOrderId', 'status'], 'string', 'max' => 255],
            [['sent_to_client'], 'integer'],
            [['create_date'], 'safe'],
        ];
    }

    public function add(
        string $orderId,
        string $extOrderId,
        string $status = '',
        string $waybill = '',
        int $sentToClient = 0
    ): bool {
        $model = new self();

        $model->order_id       = $orderId;
        $model->extOrderId     = $extOrderId;
        $model->status         = $status;
        $model->waybill        = $waybill;
        $model->sent_to_client = $sentToClient;
        $model->create_date    = date('Y-m-d H:i:s');

        if (!$model->save()) {
            \Yii::error([
                'errors' => $model->errors,
                'data'   => $model->attributes,
            ], 'kaspi');

            return false;
        }

        return true;
    }

}
