<?php
namespace app\assets;

use yii\web\AssetBundle;

class ReportsAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl  = '@web';

    public $js = [
      'js/reports.js?v=1.1',
    ];

    public $depends = [
        'app\assets\AppAsset', // подтянет jQuery, Bootstrap и общие стили/скрипты
    ];
}
