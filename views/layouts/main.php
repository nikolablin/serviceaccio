<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\bootstrap5\Modal;
use yii\widgets\ActiveForm;
use yii\web\YiiAsset;
use yii\helpers\Url;
YiiAsset::register($this);
AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);

$userGroup = Yii::$app->user->identity->group->id ?? null;
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
  <title><?= Html::encode($this->title) ?></title>
  <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
  <div id="container">
    <?php $this->beginBody() ?>

    <header>
      <div>
        <div class="logotype"><span></span></div>
        <div class="account" data-guest="<?=((string)Yii::$app->user->isGuest);?>">
          <?php
          switch(Yii::$app->user->isGuest){
            case true:
              // echo Html::a('Создать&nbsp;аккаунт', ['site/signup'], ['class' => 'link-blue']);
              echo Html::a('Войти', ['site/login'], ['class' => 'btn btn-black']);
              break;
            case false:
              echo Html::a(Yii::$app->user->identity->name, ['site/profile'], ['class' => 'text-secondary text-decoration-none']);

              echo '<div>' . Html::beginForm(['/site/logout'], 'post') .
                              Html::submitButton('Выйти', ['class' => 'btn btn-link p-0']) .
                              Html::endForm() . '</div>';
              break;
          }
          ?>
        </div>
      </div>
      <?php
      switch(Yii::$app->user->isGuest){
        case false:
          ?>
          <div class="topmenu">
            <?php
            NavBar::begin([ 'options' => [ 'class' => '' ]]);

            $menuItems = [];
            if (in_array($userGroup, [1, 2])) {
              $menuItems[] = ['label' => 'Отчеты', 'url' => ['/reports'], 'active' => $this->context->route == 'site/reports'];
            }
            if (in_array($userGroup, [1, 2])) {
              $menuItems[] = ['label' => 'Пользователи', 'url' => ['/users'], 'active' => $this->context->route == 'site/users'];
            }
            if(in_array($userGroup,[1,5])){
              $menuItems[] = ['label' => 'Бухгалтерия', 'url' => ['/accountment'], 'active' => $this->context->route == 'site/accountment'];
            }
            if(in_array($userGroup,[1,2])){
              $menuItems[] = ['label' => 'Конфигурация заказов', 'url' => ['/ordersconfig'], 'active' => $this->context->route == 'site/ordersconfig'];
            }
            echo Nav::widget([
                  'items' => $menuItems,
                  'options' => ['class' => ''],
              ]);

            NavBar::end();
            ?>
          </div>
        <?php } ?>
    </header>

    <div id="content"><?=$content;?></div>

    <footer>
      <div>
        <div class="copyrights"><?=date('Y')?> © ACCIO STORE SERVICE. Все права защищены. </div>
      </div>
    </footer>

    <?php $this->endBody() ?>
  </div>
</body>
</html>
<?php $this->endPage() ?>
