<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $ms_id
 * @property string|null $order_ms_id
 * @property string $state_id
 * @property string $created_at
 * @property string|null $updated_at
 */
class V2Demands extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%v2_demands}}';
    }

    public function rules(): array
    {
        return [
            [['ms_id', 'state_id'], 'required'],
            [['ms_id', 'order_ms_id', 'state_id'], 'string', 'max' => 36],
            [['ms_id'], 'unique'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // Т.к. в таблице updated_at не обновляется автоматически — делаем вручную
        $this->updated_at = date('Y-m-d H:i:s');

        return true;
    }
}
