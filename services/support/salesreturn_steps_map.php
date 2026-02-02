<?php
use app\services\steps\salesreturn\Finish as SalesreturnFinish;

return [
   Yii::$app->params['moyskladv2']['salesreturn']['states']['finish'] => SalesreturnFinish::class,
];
