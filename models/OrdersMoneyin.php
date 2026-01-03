<?php

namespace app\models;

use yii\db\ActiveRecord;

class OrdersMoneyin extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%orders_moneyin}}';
    }
}
