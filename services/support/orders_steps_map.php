<?php
use app\services\steps\orders\ApproveToDemand as OrderApproveToDemand;
use app\services\steps\orders\Assembled as OrderAssembled;
use app\services\steps\orders\Back as OrderBack;
use app\services\steps\orders\Canceled as OrderCanceled;
use app\services\steps\orders\Completed as OrderCompleted;
use app\services\steps\orders\InJob as OrderInJob;
use app\services\steps\orders\InvoiceIssued as OrderInvoiceIssued;
use app\services\steps\orders\TakeToJob as OrderTakeToJob;

return [
   Yii::$app->params['moyskladv2']['orders']['states']['approvetodemand'] => OrderApproveToDemand::class,
   Yii::$app->params['moyskladv2']['orders']['states']['assembled'] => OrderAssembled::class,
   Yii::$app->params['moyskladv2']['orders']['states']['back'] => OrderBack::class,
   Yii::$app->params['moyskladv2']['orders']['states']['canceled'] => OrderCanceled::class,
   Yii::$app->params['moyskladv2']['orders']['states']['completed'] => OrderCompleted::class,
   Yii::$app->params['moyskladv2']['orders']['states']['injob'] => OrderInJob::class,
   Yii::$app->params['moyskladv2']['orders']['states']['invoiceissued'] => OrderInvoiceIssued::class,
   Yii::$app->params['moyskladv2']['orders']['states']['taketojob'] => OrderTakeToJob::class,
];
