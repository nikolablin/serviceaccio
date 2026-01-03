<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class UserGroups extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%user_groups}}';
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getGroup()
    {
        return $this->hasOne(UGroups::class, ['id' => 'group_id']);
    }
}
