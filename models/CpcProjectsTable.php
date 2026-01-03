<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use app\models\CpcProjectsDataTable;

class CpcProjectsTable extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%marketing_cpc_projects}}';
    }

    public function projectTypes()
    {
      return [
        '1' => 'Поиск',
        '2' => 'Умная кампания',
        '3' => 'Торговая кампания',
        '4' => 'Баннеры',
        '5' => 'Максимальная эффективность'
      ];
    }

    public function getProjectTypeById($tid)
    {
      $list = self::projectTypes();

      foreach ($list as $lkey => $l) {
        if($lkey == $tid){
          return $l;
          break;
        }
      }
    }

    public function getProjectsList()
    {
      $data = self::find()->all();
      return $data;
    }

    public function wrapCpcProject($project)
    {
      $html =  '<div class="project alert alert-warning p-2 pe-5" data-project-id="' . $project->id . '">
                  <div class="title">' . $project->title . '</div>
                  <div class="type">' . self::getProjectTypeById($project->type) . '</div>
                  <div class="pid">' . $project->pid . '</div>
                  <button type="button" class="position-absolute top-0 end-0 p-2 remove-cpc-project btn-close" aria-label="Закрыть"></button>
                </div>';

      return $html;
    }

    public function removeCpcProject($prid)
    {
      $cpc = self::findOne($prid);

      if ($cpc) {
        $cpc->delete();

        $cpcdata = new CpcProjectsDataTable();

        $cpcdataModel = $cpcdata->deleteAll(['cpc_id' => $prid]);

        if($cpcdataModel !== false){
          return true;
        }
        else {
          return false;
        }
      }

      return false;
    }
}
