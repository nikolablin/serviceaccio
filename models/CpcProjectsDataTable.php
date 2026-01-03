<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class CpcProjectsDataTable extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%marketing_cpc_projects_data}}';
    }

    public function getMarketingCpcData($pid,$date)
    {
      $month  = $date->format('m');
      $year   = $date->format('Y');

      $data = self::findOne(['cpc_id' => $pid, 'year' => $year, 'month' => $month]);

      return $data;
    }

    public function getMarketingCpcDataArray()
    {
      $data_columns = [
        'cpc_data_1_1' => 'Показы',
        'cpc_data_1_2' => 'Клики',
        'cpc_data_1_3' => 'Стоимость рекламы, Тенге',
        'cpc_data_1_4' => 'Добавление в корзину, шт',
        'cpc_data_1_5' => 'WhatsApp / переходы, шт',
        'cpc_data_1_6' => 'Binotel / звонки, шт',
        'cpc_data_1_7' => 'Purchase / покупки на сайте, шт',
        'cpc_data_1_8' => 'Binotel / покупки, шт',
        // 'cpc_data_1_9' => 'WhatsApp / покупки, шт',
        'cpc_data_1_10' => 'Purchase / покупки на сайте, Тенге',
        'cpc_data_1_11' => 'Binotel / ≧ покупки, Тенге',
      ];

      return $data_columns;
    }

    public function separateFillingPeriodsForFields()
    {
      $data_columns = [
        'cpc_data_1_1' => 'weekly',
        'cpc_data_1_2' => 'weekly',
        'cpc_data_1_3' => 'weekly',
        'cpc_data_1_4' => 'weekly',
        'cpc_data_1_5' => 'weekly',
        'cpc_data_1_6' => 'weekly',
        'cpc_data_1_7' => 'weekly',
        'cpc_data_1_8' => 'weekly',
        // 'cpc_data_1_9' => 'weekly',
        'cpc_data_1_10' => 'weekly',
        'cpc_data_1_11' => 'weekly'
      ];

      return $data_columns;
    }

    public function getMarketingCpcYearData($pid,$year)
    {
      return self::find()
                    ->where(['cpc_id' => $pid, 'year' => $year])
                    ->orderBy(['month' => SORT_ASC]) // Сортировка по возрастанию месяца
                    ->all();
    }

    public function setMarketingEmptyCpcProjectMonthYearRec($cpcprId,$month,$year,$countWeeks)
    {
      $dataSeparators = str_repeat(':::',($countWeeks-1));
      $model = new self();
      $model->cpc_id = $cpcprId;
      $model->year = $year;
      $model->month = $month;
      $model->create_date = date('Y-m-d H:i:s');

      foreach (range(1,11) as $r) {
        $dataName = 'cpc_data_1_' . $r;
        $model->$dataName = $dataSeparators;
      }

      $model->save();

      return $model;
    }
}
