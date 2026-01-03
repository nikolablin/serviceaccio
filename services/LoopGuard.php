<?php

namespace app\services;

class LoopGuard
{
    public static function isBlocked(?string $until): bool
    {
        return !empty($until) && strtotime($until) > time();
    }

    public static function until(int $ttl): string
    {
        return date('Y-m-d H:i:s', time() + $ttl);
    }
}
