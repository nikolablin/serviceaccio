<?php
namespace app\assets;

use yii\web\AssetBundle;

class AccountmentAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl  = '@web';

    public $js = [
      'js/accountment.js?v=1.1',
    ];

    public $depends = [
        'app\assets\AppAsset', // подтянет jQuery, Bootstrap и общие стили/скрипты
    ];
}
