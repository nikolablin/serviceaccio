<?php
namespace app\services\support;

use Yii;

class StepRouter
{
    /** @var array<string, class-string<StepInterface>> */
    private array $map;

    /** @param array<string, class-string<StepInterface>> $map */
    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function resolve(?string $stateId): ?StepInterface
    {
        if (!$stateId) return null;

        $class = $this->map[$stateId] ?? null;
        if (!$class) return null;

        // Можно просто new $class(); но Yii::createObject удобнее, если потом DI появится
        return Yii::createObject($class);
    }
}
