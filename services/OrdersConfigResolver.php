<?php

namespace app\services;

use Yii;
use app\models\OrdersConfigTable;

class OrdersConfigResolver
{
    /**
     * Возвращает конфиг:
     * - если по проекту 1 запись — возвращает её
     * - если записей > 1 — выбирает по (project + channel + payment_type)
     */
    public function resolve(object $order): ?OrdersConfigTable
    {
        $projectId = $this->projectId($order);
        if (!$projectId) return null;

        // 1) если по проекту ровно 1 конфиг — берём его
        $cnt = (int)OrdersConfigTable::find()->where(['project' => $projectId])->count();
        if ($cnt === 1) {
            return OrdersConfigTable::find()->where(['project' => $projectId])->one();
        }

        // 2) если конфигов несколько — нужна детализация
        $paymentTypeId = $this->paymentTypeId($order);
        $channelId     = $this->channelValueId($order);

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

    private function paymentTypeId(object $order): ?string
    {
        $href = $order->paymentType->meta->href ?? null;
        return $href ? basename($href) : null;
    }

    /**
     * Возвращает ID значения customentity (как у тебя 8225... на скрине)
     * из атрибута "☎️ Каналы связи"
     */
    private function channelValueId(object $order): ?string
    {
        $attrId = Yii::$app->params['moysklad']['channelAttrId'] ?? null;
        if (!$attrId) return null;

        foreach (($order->attributes ?? []) as $attr) {
            if (($attr->id ?? null) !== $attrId) continue;

            // value.meta.href содержит .../customentity/<entityId>/<VALUE_ID>
            $href = $attr->value->meta->href ?? null;
            return $href ? basename($href) : null;
        }

        return null;
    }
}
