<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $moysklad_id
 * @property string $from_area
 * @property int    $type
 * @property float  $sum
 * @property string $create_date
 *
 * @property OrdersProducts[] $products
 * @property OrdersClients    $client
 */
class Orders extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%orders}}';
    }

    public function rules()
    {
        return [
            [['moysklad_id', 'from_area', 'type', 'sum', 'create_date'], 'required'],
            [['type'], 'integer'],
            [['sum'], 'number'],
            [['create_date'], 'safe'],

            [['moysklad_id', 'from_area'], 'string', 'max' => 255],

            [['moysklad_id'], 'unique'],
        ];
    }

    /* --------------------
     * Relations
     * ------------------*/

    public function getProducts()
    {
        return $this->hasMany(OrdersProducts::class, ['order_id' => 'id']);
    }

    public function getClient()
    {
        return $this->hasOne(OrdersClients::class, ['order_id' => 'id']);
    }

    public static function upsertFromMs(object $MSOrder, string $fromArea, int $type = 1): int
    {
        $msId = (string)($MSOrder->id ?? '');

        if ($msId === '') {
            throw new \InvalidArgumentException('MSOrder->id is empty');
        }

        $model = static::findOne(['moysklad_id' => $msId]);
        if ($model === null) {
            $model = new static();
            $model->moysklad_id = $msId;
        }

        $model->from_area   = $fromArea !== '' ? $fromArea : '-';
        $model->type        = $type;
        $model->sum         = isset($MSOrder->sum) ? (float)$MSOrder->sum / 100 : 0.0;
        $model->create_date = !empty($MSOrder->moment) ? (string)$MSOrder->moment : date('Y-m-d H:i:s');

        if (!$model->save()) {
            throw new \RuntimeException('Orders upsert error: ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE));
        }
        return (int)$model->id;
    }
}
