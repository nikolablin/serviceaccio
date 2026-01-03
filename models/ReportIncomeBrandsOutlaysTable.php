<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class ReportIncomeBrandsOutlaysTable extends ActiveRecord
{
  public static function tableName()
  {
      return '{{%report_income_brands_outlays}}';
  }

  public function getIncomeBrandsMonthOfYearOutlays($year)
  {
    $data = self::findAll(['year' => $year]);
    return $data;
  }

  public function setYearMonthsValues($year,$monthsValues)
  {
    foreach ($monthsValues as $monthData) {
      $model = self::findOne(['year' => $year, 'month' => $monthData['month'], 'type' => $monthData['type']]);

      if (!$model) {
        $model = new self();
        $model->year = $year;
        $model->month = $monthData['month'];
        $model->type = $monthData['type'];
      }

      $model->value = $monthData['value'];

      if (!$model->save()) {
        Yii::error("Не удалось сохранить запись за " . $year . "-" . $monthData['month'] . ": " . json_encode($model->errors), __METHOD__);
      }
    }

    return true;
  }
}
