<?php
set_time_limit(0);

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Tabs;
use app\models\OrdersConfig;
use app\models\MoyskladWebhook;
use app\models\Moysklad;

$this->title = 'Конфигурация заказов';
$this->params['breadcrumbs'][] = $this->title;
$projects = $references->projects;

?>

<div class="site-orders-config">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php
    $tabs = [];
    $isFirst = true;

    foreach ($projects->rows as $project) {
      $projectId   = $project->id;
      $projectName = $project->name ?? $project->id;

      $content = '
            <section class="order-config" id="project-' . Html::encode($projectId) . '">
                <h2>' . Html::encode($projectName) . '</h2>
                ' . OrdersConfig::getOrderConfigForm($projectId,$references) . '
            </section>';

            $tabs[] = [
                'label'   => Html::encode($projectName),
                'content' => $content,
                'active'  => $isFirst,
            ];

      $isFirst = false;
    }

    echo Tabs::widget([
        'items' => $tabs,
        'options' => [
            'class' => 'order-config-tabs'
        ],
        'headerOptions' => [
            'class' => 'nav-tabs order-tabs-nav'
        ],
        'itemOptions' => [
            'class' => 'tab-pane order-tab-pane'
        ],
    ]);

    ?>
</div>
