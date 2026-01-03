<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'Создать пользователя';
?>

<div class="site-register">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin([
      'options' => ['autocomplete' => 'off']
    ]); ?>

        <?= $form->field($model, 'name')->label('Имя и фамилия') ?>
        <?= $form->field($model, 'username')->label('Логин')->textInput(['autocomplete' => 'off']) ?>
        <?= $form->field($model, 'email')->label('Адрес электронной почты') ?>
        <?= $form->field($model, 'password')->label('Пароль')->passwordInput()->textInput(['autocomplete' => 'off']) ?>
        <?= $form->field($model, 'confirm_password')->label('Пароль еще раз')->passwordInput()->textInput(['autocomplete' => 'off']) ?>

        <?= $form->field($model, 'group_id')
            ->label('Группа')
            ->radioList(
                $groups,
                [
                    'item' => function ($index, $label, $name, $checked, $value) {
                        return '<div class="form-check form-check-inline">' .
                            Html::radio($name, $checked, ['value' => $value, 'class' => 'form-check-input', 'id' => "group-$value"]) .
                            Html::label($label, "group-$value", ['class' => 'form-check-label']) .
                            '</div>';
                    },
                ]
            ) ?>

        <div class="form-group submits">
            <?= Html::submitButton('Создать аккаунт', ['class' => 'btn btn-primary']) ?>
        </div>

    <?php ActiveForm::end(); ?>
</div>
