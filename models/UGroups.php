<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class UGroups extends ActiveRecord
{
    /**
     * Связываем модель с таблицей `groups`.
     *
     * @return string
     */
    public static function tableName()
    {
        return '{{%groups}}';
    }

    /**
     * Правила валидации для полей.
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255]
        ];
    }

    /**
     * Связь с пользователями через таблицу `user_groups`.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasMany(User::class, ['id' => 'user_id'])
            ->viaTable('user_groups', ['group_id' => 'id']);
    }
}
