<?php
namespace app\models;

use yii\db\ActiveRecord;

class CashRegisterShifts extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%orders_cash_register_shifts}}';
    }
}
