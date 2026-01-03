<?php
namespace app\assets;

use yii\web\AssetBundle;

class OrdersConfigAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl  = '@web';

    public $js = [
      'js/ordersconfigs.js?v=1.1',
    ];

    public $depends = [
        'app\assets\AppAsset', // подтянет jQuery, Bootstrap и общие стили/скрипты
    ];
}
