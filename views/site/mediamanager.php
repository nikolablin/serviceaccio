<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\grid\GridView;

/** @var $model \app\models\MediaUploadForm */
/** @var $categories array */
/** @var $dataProvider \yii\data\ActiveDataProvider */

$this->title = 'Медиаменеджер';
?>

<div id="site-mediamanager">
  <h1><?= Html::encode($this->title) ?></h1>

  <?php if (Yii::$app->session->hasFlash('success')): ?>
    <div class="alert alert-success">
      <?= Yii::$app->session->getFlash('success') ?>
    </div>
  <?php endif; ?>

  <?php $form = ActiveForm::begin([
      'options' => ['enctype' => 'multipart/form-data', 'class' => 'border p-5 bg-light'],
  ]); ?>

    <div class="mb-4 col-6">
      <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>
    </div>

    <div class="mb-5 col-10 d-flex gap-4 align-items-end">
      <?= $form->field($model, 'category_id')->dropDownList(
          $categories,
          ['prompt' => '— Выберите категорию —', 'class' => 'form-control form-select']
      ) ?>
      <small class="text-muted">или</small>
      <?= $form->field($model, 'new_category')->textInput([
          'placeholder' => 'Или введите новую категорию…',
          'maxlength' => true,
      ]) ?>
    </div>

    <div class="mb-5 col-6">
      <?= $form->field($model, 'file')->fileInput() ?>
  </div>

    <div class="form-group">
      <?= Html::submitButton('Сохранить', ['class' => 'btn btn-sm btn-dark']) ?>
    </div>

  <?php ActiveForm::end(); ?>

  <hr>

  <h5 class="mt-4 mb-3">Все файлы</h5>

  <?= GridView::widget([
      'dataProvider' => $dataProvider,
      'columns' => [
          ['class' => 'yii\grid\SerialColumn'],

          // 'id',
          [
              'attribute' => 'title',
              'label' => Yii::t('app', 'Название'),
              'sortLinkOptions' => [
                  'class' => 'sort-link text-primary fw-normal',
              ],
          ],
          [
              'attribute' => 'file_type',
              'label' => 'Тип',
              'sortLinkOptions' => [
                  'class' => 'sort-link text-primary fw-normal',
              ],
          ],
          [
              'label' => 'Категория',
              'value' => function($m) {
                  return $m->category->name ?? '—';
              }
          ],
          [
              'label' => 'Файл',
              'format' => 'raw',
              'value' => function ($model) {
                  $url = $model->getUrl(true); // абсолютный URL для копирования

                  return
                      \yii\helpers\Html::a(
                          'Открыть',
                          $url,
                          ['target' => '_blank', 'rel' => 'noopener', 'class' => 'text-primary']
                      )
                      .
                      '<span class="copy-link ms-2 text-muted"
                            data-url="' . \yii\helpers\Html::encode($url) . '"
                            title="Скопировать ссылку"
                            style="cursor:pointer;">
                          <i class="fa-regular fa-copy"></i>
                       </span>';
              }
          ],
          [
              'attribute' => 'created_at',
              'label' => Yii::t('app', 'Дата создания'),
              'sortLinkOptions' => [
                  'class' => 'sort-link text-primary fw-normal',
              ],
              'format' => 'datetime',
          ],
          [
              'class' => 'yii\grid\ActionColumn',
              'template' => '{delete}',
              'buttons' => [
                  'delete' => function ($url, $model) {
                      return \yii\helpers\Html::a(
                          '<i class="fa-solid fa-trash text-danger"></i>',
                          ['/media/delete', 'id' => $model->id],
                          [
                              'title' => 'Удалить файл',
                              'data-confirm' => 'Удалить файл без возможности восстановления?',
                              'data-method' => 'post',
                              'data-pjax' => '0',
                          ]
                      );
                  },
              ],
              'headerOptions' => ['style' => 'width:60px; text-align:center'],
              'contentOptions' => ['style' => 'text-align:center'],
          ],
      ],
  ]); ?>
</div>
