<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $order_id
 * @property string $product_code
 * @property int    $quantity
 * @property float  $cost
 *
 * @property Orders $order
 */
class OrdersProducts extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%orders_products}}';
    }

    public function rules()
    {
        return [
            [['order_id', 'product_code', 'quantity', 'cost'], 'required'],
            [['order_id', 'quantity'], 'integer'],
            [['cost'], 'number'],
            [['product_code'], 'string', 'max' => 255],
        ];
    }

    public function getOrder()
    {
        return $this->hasOne(Orders::class, ['id' => 'order_id']);
    }

    public static function syncFromMs(int $orderId, object $MSOrder): void
    {
        static::deleteAll(['order_id' => $orderId]);

        $rows = $MSOrder->positions->rows ?? [];
        if (empty($rows)) {
            return;
        }

        $batch = [];

        foreach ($rows as $pos) {
            $assortment = $pos->assortment ?? null;
            if (!$assortment || empty($assortment->meta->href)) {
                continue;
            }

            // ✅ ГЛАВНЫЙ идентификатор товара
            $moyskladProductId = basename($assortment->meta->href);

            // дополнительный код (не критичный)
            $productCode =
                $assortment->code
                ?? $assortment->article
                ?? $assortment->externalCode
                ?? '-';

            $qty = isset($pos->quantity) ? (float)$pos->quantity : 0;

            // цена в МС — в копейках
            $cost = 0.0;
            if (isset($pos->price)) {
                $cost = (float)$pos->price / 100;
            } elseif (isset($pos->cost)) {
                $cost = (float)$pos->cost / 100;
            }

            $batch[] = [
                $orderId,
                $moyskladProductId,
                $productCode,
                $qty,
                $cost,
            ];
        }

        if ($batch) {
            static::getDb()->createCommand()->batchInsert(
                static::tableName(),
                [
                    'order_id',
                    'moysklad_product_id',
                    'product_code',
                    'quantity',
                    'cost',
                ],
                $batch
            )->execute();
        }
    }

    public static function syncFromMsDemand(int $orderId, object $demand): void
    {
        static::deleteAll(['order_id' => $orderId]);

        $rows = $demand->positions->rows ?? [];
        if (empty($rows)) {
            return;
        }

        $batch = [];

        foreach ($rows as $pos) {
            $assortment = $pos->assortment ?? null;
            $href = $assortment->meta->href ?? null;
            if (!$href) {
                continue;
            }

            $moyskladProductId = basename((string)$href);

            $productCode =
                $assortment->code
                ?? $assortment->article
                ?? $assortment->externalCode
                ?? '-';

            $qty = isset($pos->quantity) ? (float)$pos->quantity : 0;

            $cost = 0.0;
            if (isset($pos->price)) {
                $cost = (float)$pos->price / 100;
            } elseif (isset($pos->cost)) {
                $cost = (float)$pos->cost / 100;
            }

            $batch[] = [$orderId, $moyskladProductId, $productCode, $qty, $cost];
        }

        if ($batch) {
            static::getDb()->createCommand()->batchInsert(
                static::tableName(),
                ['order_id','moysklad_product_id','product_code','quantity','cost'],
                $batch
            )->execute();
        }
    }

}
