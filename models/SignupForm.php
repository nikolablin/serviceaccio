<?php

namespace app\models;

use Yii;
use yii\base\Model;
use app\models\User; // если у вас есть модель User для сохранения данных

class SignupForm extends Model
{
    public $name;
    public $username;
    public $email;
    public $password;
    public $confirm_password;
    public $group_id = 3;

    public function rules()
    {
      return [
              [['name','username', 'email', 'password', 'confirm_password'], 'required'],
              ['email', 'email', 'message' => 'Введите правильный email адрес'],
              ['name', 'string', 'min' => 3, 'max' => 255],
              ['username', 'string', 'min' => 3, 'max' => 255],
              ['password', 'string', 'min' => 6],
              ['confirm_password', 'compare', 'compareAttribute' => 'password', 'message' => 'Пароли не совпадают'],
          ];
    }
 
    public function register()
    {
        if ($this->validate()) {
            $user = new User();
            $user->name = $this->name;
            $user->username = $this->username;
            $user->email = $this->email;
            $user->password = $this->password; // Устанавливаем пароль для выполнения валидации
            $user->setPassword($this->password); // Хэшируем пароль
            $user->generateAuthKey(); // Генерируем ключ аутентификации
            if ($user->save()) {
                return true;
            } else {
                Yii::error('Error saving user: ' . json_encode($user->errors), __METHOD__);
                print_r($user->errors); // Печатаем ошибки на экран
            }
        }
        return false;
    }
}
