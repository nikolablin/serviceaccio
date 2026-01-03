<?php

namespace app\models;

use Yii;
use yii\base\Model;
use app\models\User;

class ChangePasswordForm extends Model
{
    public $oldPassword;
    public $newPassword;
    public $confirmPassword;

    /**
     * @var User
     */
    private $_user;

    public function __construct($user, $config = [])
    {
        $this->_user = $user;
        parent::__construct($config);
    }

    public function rules()
    {
        return [
            [['oldPassword', 'newPassword', 'confirmPassword'], 'required'],
            ['oldPassword', 'validateOldPassword'],
            ['confirmPassword', 'compare', 'compareAttribute' => 'newPassword', 'message' => 'Пароли не совпадают.'],
            [['newPassword', 'confirmPassword'], 'string', 'min' => 6],
        ];
    }

    public function validateOldPassword($attribute, $params)
    {
        if (!$this->_user || !Yii::$app->security->validatePassword($this->oldPassword, $this->_user->password_hash)) {
            $this->addError($attribute, 'Неверный текущий пароль.');
        }
    }

    public function changePassword()
    {
        if (!$this->validate()) {
            return false;
        }

        $this->_user->password_hash = Yii::$app->security->generatePasswordHash($this->newPassword);
        return $this->_user->save(false);
    }
}
