<?php

namespace app\services;

use Yii;
use app\models\OrdersConfigTable;
use app\models\Moysklad;

class OrdersConfigResolver
{
    /**
     * Возвращает конфиг:
     * - если по проекту 1 запись — возвращает её
     * - если записей > 1 — выбирает по (project + channel + payment_type)
     */
    public function resolve(object $order): ?OrdersConfigTable
    {
        $projectId  = $this->projectId($order);
        $moysklad   = new Moysklad();

        if (!$projectId) return null;

        // 1) если по проекту ровно 1 конфиг — берём его
        $cnt = (int)OrdersConfigTable::find()->where(['project' => $projectId])->count();
        if ($cnt === 1) {
            return OrdersConfigTable::find()->where(['project' => $projectId])->one();
        }

        // 2) если конфигов несколько — нужна детализация
        $paymentAttrId = Yii::$app->params['moysklad']['paymentTypeAttrId'] ?? null;
        $channelAttrId = Yii::$app->params['moysklad']['channelAttrId'] ?? null;

        $paymentTypeId = $paymentAttrId ? $moysklad->getAttributeValueId($order, $paymentAttrId) : null;
        $channelId     = $channelAttrId ? $moysklad->getAttributeValueId($order, $channelAttrId) : null;

        if (!$paymentTypeId || !$channelId) {
            // для multi-config проектов без этих полей — конфиг не выбрать
            return null;
        }

        // 3) точный матч: project + payment_type + channel
        $config = OrdersConfigTable::find()->where([
            'project'      => $projectId,
            'payment_type' => $paymentTypeId,
            'channel'      => $channelId,
        ])->one();

        if ($config) return $config;

        // 4) Фолбэк (если вдруг channel не заполнен в конфиге или в заказе):
        // можно пробовать хотя бы по payment_type (на твой выбор)
        $fallback = Yii::$app->params['moysklad']['configFallbackByPaymentType'] ?? false;
        if ($fallback) {
            return OrdersConfigTable::find()->where([
                'project'      => $projectId,
                'payment_type' => $paymentTypeId,
            ])->one();
        }

        return null;
    }

    private function projectId(object $order): ?string
    {
        $href = $order->project->meta->href ?? null;
        return $href ? basename($href) : null;
    }
}
