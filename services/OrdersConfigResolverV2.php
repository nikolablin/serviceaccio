<?php
namespace app\services;

use Yii;
use app\models\OrdersConfigTable;
use app\models\MoyskladV2;

class OrdersConfigResolverV2
{
    public function resolve(object $order, MoyskladV2 $ms): ?OrdersConfigTable
    {
        $projectHref = $order->project->meta->href ?? null;
        $projectId   = $projectHref ? basename($projectHref) : null;
        if (!$projectId) {
            return null;
        }

        // 1) Все конфиги проекта
        $configs = OrdersConfigTable::find()
            ->where(['project' => $projectId])
            ->all();

        if (!$configs) {
            return null;
        }

        // если ровно один — он
        if (count($configs) === 1) {
            return $configs[0];
        }

        // 2) Достаём channel/payment из заказа
        $paymentAttrId = Yii::$app->params['moyskladv2']['orders']['attributesFields']['paymentType'] ?? null;
        $channelAttrId = Yii::$app->params['moyskladv2']['orders']['attributesFields']['channel'] ?? null;

        $paymentTypeId = $paymentAttrId ? $ms->getAttributeValue($order, (string)$paymentAttrId) : null;
        $channelId     = $channelAttrId ? $ms->getAttributeValue($order, (string)$channelAttrId) : null;


        // 3) Определяем "сайт" / "не сайт"
        $channelIsWebsite = Yii::$app->params['moyskladv2']['staticReferenceValues']['channelIsWebsite'] ?? null;

        // Если канал не "Сайт" -> работаем только с byhand-конфигами
        $shouldUseByhand = true;
        if ($channelId && $channelIsWebsite && (string)$channelId === (string)$channelIsWebsite) {
            $shouldUseByhand = false;
        }

        $isByhand = static function ($v): bool {
            return $v === 'byhand' || $v === 0 || $v === '0' || $v === null || $v === '';
        };

        // 4) Сужаем пул конфигов по правилу канала
        if ($shouldUseByhand) {
            $configs = array_values(array_filter($configs, static function ($cfg) use ($isByhand) {
                return $isByhand($cfg->channel ?? null);
            }));
        } else {
            // Сайт: канал должен быть реальным id и совпадать с channelId
            $configs = array_values(array_filter($configs, static function ($cfg) use ($channelId, $isByhand) {
                $ch = $cfg->channel ?? null;
                if ($isByhand($ch)) return false;
                return $channelId && (string)$ch === (string)$channelId;
            }));
        }

        if (!$configs) {
            return null;
        }

        // 5) Матчим по оплате
        if ($paymentTypeId) {
            $matched = array_values(array_filter($configs, static function ($cfg) use ($paymentTypeId) {
                return (string)($cfg->payment_type ?? '') === (string)$paymentTypeId;
            }));

            if (count($matched) === 1) {
                return $matched[0];
            }

            if (count($matched) > 1) {
                return $this->pickFirstStable($matched);
            }
        }

        // 6) Если paymentTypeId пуст или точного матча нет:
        // можно вернуть единственный оставшийся конфиг, иначе null (чтобы не гадать)
        if (count($configs) === 1) {
            return $configs[0];
        }

        return null;
    }

    private function pickFirstStable(array $configs): OrdersConfigTable
    {
        usort($configs, static function ($a, $b) {
            return ((int)$a->id) <=> ((int)$b->id);
        });
        return $configs[0];
    }
}
