<?php
namespace app\models;

use yii\db\ActiveRecord;

class OrdersDemands extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%orders_demands}}'; // при tablePrefix=acs43_ будет acs43_demands
    }

    public function rules()
    {
        return [
            [['order_id','moysklad_order_id','moysklad_demand_id','created_at','updated_at'], 'required'],
            [['order_id'], 'integer'],
            [['created_at','updated_at'], 'safe'],
            [['moysklad_order_id','moysklad_demand_id'], 'string', 'max' => 36],
            [['order_id'], 'unique'],
            [['moysklad_order_id'], 'unique'],
            [['moysklad_demand_id'], 'unique'],
        ];
    }
}
