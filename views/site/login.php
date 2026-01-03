<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'Вход в сервис';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-login">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin([
        'id' => 'login-form',
    ]); ?>

        <div class="form-group">
          <?= $form->field($model, 'username')->textInput(['autofocus' => true])->label('Имя пользователя') ?>
        </div>

        <div class="form-group">
          <?= $form->field($model, 'password')->passwordInput()->label('Пароль') ?>
        </div>

        <div class="form-group">
          <?= $form->field($model, 'rememberMe')->checkbox([
            // 'template' => "<div class=\"offset-lg-2 col-lg-10\">{input} {label}</div>\n<div class=\"col-lg-12\">{error}</div>",
            'template' => "<div>{input} {label}</div>\n<div class=\"col-lg-12\">{error}</div>",
            ])->label('Запомнить меня') ?>
        </div>

        <div class="form-group">
            <?= Html::submitButton('Войти в сервис', ['class' => 'btn btn-black', 'name' => 'login-button']) ?>
        </div>

    <?php ActiveForm::end(); ?>
</div>
