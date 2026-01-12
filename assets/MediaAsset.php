<?php
namespace app\assets;

use yii\web\AssetBundle;

class MediaAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl  = '@web';

    public $js = [
      'js/media.js?v=1.2',
    ];

    public $depends = [
        'app\assets\AppAsset', // подтянет jQuery, Bootstrap и общие стили/скрипты
    ];
}
