<?php
use app\services\steps\factureout\Created as FactureoutCreated;

return [
   Yii::$app->params['moyskladv2']['factureOut']['states']['created'] => FactureoutCreated::class,
];
