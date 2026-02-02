<?php
use app\services\steps\demands\AcceptBack as DemandAcceptBack;
use app\services\steps\demands\Assembled as DemandAssembled;
use app\services\steps\demands\BackToStock as DemandBackToStock;
use app\services\steps\demands\BackWithoutBill as DemandBackWithoutBill;
use app\services\steps\demands\Closed as DemandClosed;
use app\services\steps\demands\InvoiceIssued as DemandInvoiceIssued;
use app\services\steps\demands\ToDemand as DemandToDemand;
use app\services\steps\demands\Transferred as DemandTransferred;

return [
   Yii::$app->params['moyskladv2']['demands']['states']['acceptBack'] => DemandAcceptBack::class,
   Yii::$app->params['moyskladv2']['demands']['states']['assembled'] => DemandAssembled::class,
   Yii::$app->params['moyskladv2']['demands']['states']['backtostock'] => DemandBackToStock::class,
   Yii::$app->params['moyskladv2']['demands']['states']['backwithoutbill'] => DemandBackWithoutBill::class,
   Yii::$app->params['moyskladv2']['demands']['states']['closed'] => DemandClosed::class,
   Yii::$app->params['moyskladv2']['demands']['states']['invoiceissued'] => DemandInvoiceIssued::class,
   Yii::$app->params['moyskladv2']['demands']['states']['todemand'] => DemandToDemand::class,
   Yii::$app->params['moyskladv2']['demands']['states']['transferred'] => DemandTransferred::class,
];
