<?php
namespace app\models;

use Yii;
use yii\base\Model;

class System extends Model {

  public static function getFreeSpace() {
    $path = Yii::getAlias('@webroot');

    $free  = disk_free_space($path);
    $total = disk_total_space($path);
    $used  = $total - $free;

    return (object)array( 'free' => $free, 'total' => $total, 'used' => $used );
  }

  public static function formatBytes($bytes): string {
    return round($bytes / 1024 / 1024 / 1024, 2) . ' ГБ';
  }

}
