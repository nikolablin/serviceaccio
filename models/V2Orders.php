<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $ms_id
 * @property string $state_id
 * @property string $created_at
 * @property string $updated_at
 */
class V2Orders extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%v2_orders}}';
    }

    public function rules(): array
    {
        return [
            [['ms_id', 'state_id'], 'required'],
            [['ms_id', 'state_id'], 'string', 'max' => 36],
            [['ms_id'], 'unique'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }
}
