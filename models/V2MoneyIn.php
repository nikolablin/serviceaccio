<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Таблица: acs43_v2_moneyin
 *
 * @property int $id
 * @property string $type            paymentin|cashin
 * @property string $ms_id
 * @property string $order_ms_id
 * @property string|null $state_id
 * @property int $sum
 * @property string|null $payload
 * @property string $created_at
 * @property string $updated_at
 */
class V2MoneyIn extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%v2_moneyin}}';
    }

    public function rules(): array
    {
        return [
            [['type', 'ms_id', 'order_ms_id'], 'required'],
            [['payload'], 'string'],
            [['sum'], 'integer'],

            [['type'], 'string', 'max' => 16],
            [['ms_id', 'order_ms_id', 'state_id'], 'string', 'max' => 36],

            [['created_at', 'updated_at'], 'safe'],

            ['type', 'in', 'range' => ['paymentin', 'cashin']],
            [['ms_id'], 'unique'],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            if (empty($this->created_at)) {
                $this->created_at = date('Y-m-d H:i:s');
            }
        }
        $this->updated_at = date('Y-m-d H:i:s');

        return parent::beforeSave($insert);
    }
}
