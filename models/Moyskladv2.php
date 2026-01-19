<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\OrdersDemands;

class Moyskladv2 extends Model
{

  public function getMSLoginPassword()
  {
    return (object)array('login' => YII::$app->params['moyskladv2']['login'], 'password' => YII::$app->params['moyskladv2']['password']);
  }

}
