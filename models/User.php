<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class User extends ActiveRecord implements IdentityInterface
{
    public $password;
    public $group_id;

    public function rules()
    {
      return [
                [['username', 'email', 'password'], 'required'],
                [['username', 'email'], 'unique'],
                ['email', 'email'],
                ['password', 'string', 'min' => 6],
            ];
    }

    public static function tableName()
    {
      return '{{%user}}';
    }


    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($this->isNewRecord) {
                $this->auth_key = Yii::$app->security->generateRandomString();
                $this->password_hash = Yii::$app->security->generatePasswordHash($this->password);
            }
            return true;
        }
        return false;
    }

    public function setPassword($password)
    {
      // Используем встроенную функцию Yii2 для хеширования пароля
      $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString(); // генерируем случайную строку
    }

    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username]);
    }

    public function validatePassword($password)
    {
      return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        // реализация для API, если нужно
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return $this->auth_key === $authKey;
    }

    public function getGroups()
    {
      return $this->hasMany(UGroups::class, ['id' => 'group_id'])->via('userGroups');
    }

    public function getGroup()
    {
        return $this->hasOne(UGroups::class, ['id' => 'group_id'])
            ->via('userGroups');
    }

    public function getUserGroups()
    {
      return $this->hasMany(UserGroups::class, ['user_id' => 'id']);
    }

    public function setNewPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
        return $this->save(false);
    }
}
