<?php

namespace app\models;

use yii\db\ActiveRecord;

class OrdersReceipts extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%orders_receipts}}';
    }
}
