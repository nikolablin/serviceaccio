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

    public static function findByCode(string $code): ?self
    {
        return self::find()->where(['order_id' => $code])->limit(1)->one();
    }

    public function isReviewAlreadySent(string $orderCode): bool
    {
      $order = self::find()
          ->select(['sent_to_client'])
          ->where(['order_id' => $orderCode])
          ->limit(1)
          ->one();

      if (!$order) {
          return true;
      }

      return (int)$order->sent_to_client === 1;
    }

    public function markSentToClient(): bool
    {
        if ((int)$this->sent_to_client === 1) {
            return true;
        }

        $this->sent_to_client = 1;

        return $this->save(false, ['sent_to_client', 'updated_at']);
    }

    public function updateStatus(string $status): bool
    {
        $this->status = $status;
        return $this->save(false, ['status', 'updated_at']);
    }

    public function saveWaybill(string $waybillLink): bool
    {
        $this->waybill = $waybillLink;
        return $this->save(false, ['waybill', 'updated_at']);
    }
}
