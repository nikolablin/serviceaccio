<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Смена пароля';
?>

<h1><?= Html::encode($this->title) ?></h1>

<div class="change-password-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'oldPassword')->passwordInput() ?>
    <?= $form->field($model, 'newPassword')->passwordInput() ?>
    <?= $form->field($model, 'confirmPassword')->passwordInput() ?>

    <div class="form-group">
        <?= Html::submitButton('Сменить пароль', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
