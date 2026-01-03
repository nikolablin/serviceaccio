<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/fonts.css',
        'css/site.css?v=1.5',
        'css/jquery.datepicker.min.css'
    ];
    public $js = [
      // 'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js',
      'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
      'js/jquery.datepicker.min.js',
      'js/datepicker.ru-RU.js',
      'js/site.js?v=1.3',
      'js/jquery.mask.min.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset'
    ];
}
