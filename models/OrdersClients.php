<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $order_id
 * @property string $name
 * @property string $phone
 * @property string $contragent
 *
 * @property Orders $order
 */
class OrdersClients extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%orders_clients}}';
    }

    public function rules()
    {
        return [
            [['order_id', 'name', 'phone', 'contragent'], 'required'],
            [['order_id'], 'integer'],
            [['name', 'contragent'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 100],
        ];
    }

    public function getOrder()
    {
        return $this->hasOne(Orders::class, ['id' => 'order_id']);
    }

    public static function upsertFromMs(int $orderId, object $MSOrder): void
    {
        $agent = $MSOrder->agent ?? null;

        $name  = (string)($agent->name ?? '-');
        $phone = (string)($agent->phone ?? ($agent->mobilePhone ?? '-'));

        $agentHref = (string)($agent->meta->href ?? '');
        $contragent = $agentHref !== '' ? basename($agentHref) : '-';

        $model = static::findOne(['order_id' => $orderId]);
        if ($model === null) {
            $model = new static();
            $model->order_id = $orderId;
        }

        $model->name       = $name !== '' ? $name : '-';
        $model->phone      = $phone !== '' ? $phone : '-';
        $model->contragent = $contragent !== '' ? $contragent : '-';

        if (!$model->save()) {
            throw new \RuntimeException('OrdersClients upsert error: ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE));
        }
    }
}
