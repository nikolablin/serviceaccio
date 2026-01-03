<?php

namespace app\models;

use Yii;
use yii\base\Model;

class Website extends Model
{
  public function getProductsTree()
  {
    $tree = [
      0 => 'Аксессуары',
      1 => 'Зерновой кофе',
      '1_1' => 'Gimoka',
      '1_2' => 'Lollo',
      '1_3' => 'Vergnano',
      '1_4' => 'Borbone',
      2 => 'Молотый кофе',
      '2_1' => 'Gimoka',
      '2_2' => 'Lollo',
      '2_3' => 'Vergnano',
      '2_4' => 'Borbone',
      3 => 'Капсульный кофе Dolce Gusto',
      '3_1' => 'Gimoka',
      '3_2' => 'Kimbo',
      '3_3' => 'Lollo',
      '3_4' => 'Starbucks',
      '3_5' => 'Vergnano',
      '3_6' => 'Borbone',
      '3_7' => 'Lavazza',
      '3_8' => 'Nescafe',
      4 => 'Капсульный кофе Original',
      '4_1' => 'Gimoka',
      '4_2' => 'Illy',
      '4_3' => 'Jacobs',
      '4_4' => 'Kimbo',
      '4_5' => 'Lavazza',
      '4_6' => 'Lollo',
      '4_7' => 'L’OR',
      '4_8' => 'Nespresso',
      '4_9' => 'Starbucks',
      '4_10' => 'Vergnano',
      '4_11' => 'Borbone',
      5 => 'Капсульный кофе Vertuo',
      6 => 'Капсульный кофе Professional',
      '6_1' => 'Nespresso',
      '6_2' => 'Gimoka',
      7 => 'Капсульный кофе Lavazza Blue',
      8 => 'Чалды',
      '8_1' => 'Illy',
      '8_2' => 'LolloCaffe',
      '8_3' => 'Gimoka',
      '8_4' => 'Kimbo',
      '8_5' => 'Vergnano',
      '8_6' => 'Borbone',
      9 => 'Рожковые кофемашины',
      10 => 'Автоматические кофемашины',
      11 => 'Капсульные кофемашины Dolce Gusto',
      12 => 'Капсульные кофемашины Original',
      13 => 'Капсульные кофемашины Vertuo',
      14 => 'Капсульные кофемашины Professional',
      15 => 'Чай',
      16 => 'Шоколадные напитки',
      '16_1' => 'DG Borbone',
      '16_2' => 'DG Gimoka',
      '16_3' => 'DG Lollo',
      '16_4' => 'DG Nescafe',
      '16_5' => 'Original Borbone',
      '16_6' => 'Original Lollo',
      17 => 'Растворимый кофе'
    ];

    return $tree;
  }

  public function getProduct($pid)
  {
    $result = Yii::$app->dbExternal
                ->createCommand('SELECT * FROM `vu1dh_accio_products` WHERE `product_id` = :pid')
                ->bindValue(':pid', $pid)
                ->queryOne();

    if(!empty($result)){
      return (object)$result;
    }

    return false;
  }

  public function getProductWebDataBySku($sku)
  {
    $result = Yii::$app->dbExternal
                ->createCommand('SELECT * FROM `vu1dh_accio_products` WHERE `product_sku` = :sku')
                ->bindValue(':sku', $sku)
                ->queryOne();

    if(!empty($result)){
      return (object)$result;
    }

    return false;
  }

}
