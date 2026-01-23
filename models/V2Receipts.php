<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class V2Receipts extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%v2_receipts}}';
    }

    public function rules(): array
    {
        return [
            [['demand_ms_id', 'operation', 'status', 'created_at', 'updated_at'], 'required'],
            [['config_id', 'cashbox_id', 'section_id', 'payment_type', 'total_amount', 'attempts'], 'integer'],
            [['error_message', 'payload_json', 'response_json'], 'string'],
            [['created_at', 'updated_at', 'sent_at'], 'safe'],

            [['order_ms_id', 'demand_ms_id'], 'string', 'max' => 36],
            [['cash_register'], 'string', 'max' => 64],
            [['operation', 'status'], 'string', 'max' => 32],
            [['external_id'], 'string', 'max' => 128],

            [['demand_ms_id'], 'unique'],
        ];
    }

    public function beforeSave($insert): bool
    {
        $now = date('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $now;
        }
        $this->updated_at = $now;

        return parent::beforeSave($insert);
    }
}
