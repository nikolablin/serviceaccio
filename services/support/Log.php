<?php
namespace app\services\support;

use Yii;

class Log
{
    public static function orderCreate(string $message, array $context = []): void
    {
        self::write('order.create', $message, $context);
    }

    public static function orderUpdate(string $message, array $context = []): void
    {
        self::write('order.update', $message, $context);
    }

    public static function demandUpdate(string $message, array $context = []): void
    {
        self::write('demand.update', $message, $context);
    }

    public static function salesreturnUpdate(string $message, array $context = []): void
    {
        self::write('salesreturn.update', $message, $context);
    }

    public static function cashboxError(string $message, array $context = []): void
    {
        self::write('cashbox.error', '[CASHBOX] ' . $message, $context);
    }

    private static function write(string $category, string $message, array $context): void
    {
        $line = $message;

        if (!empty($context)) {
            $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        Yii::info($line, $category);
    }
}
