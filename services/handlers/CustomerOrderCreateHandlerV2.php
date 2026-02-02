<?php
namespace app\services\handlers;

use Yii;
use app\services\support\Context;
use app\services\support\StepRouter;
use app\services\support\Log;

class CustomerOrderCreateHandlerV2
{
    private StepRouter $router;

    public function __construct()
    {
        $this->router = new StepRouter(
            require __DIR__ . '/../support/orders_steps_map.php'
        );
    }

    public function handle(object $event): void
    {
        if (($event->meta->type ?? null) !== 'customerorder') return;
        if (($event->action ?? null) !== 'CREATE') return;

        $orderId = basename((string)($event->meta->href ?? ''));
        if ($orderId === '') return;

        if (!$this->acquireOrderLock($orderId, 180)) {
            Log::orderCreate('CREATE: skipped by cache lock', ['orderId' => $orderId]);
            return;
        }

        try {
            $ctx   = new Context($event);
            $order = $ctx->getOrder();

            if (!$order) {
                Log::orderCreate('CREATE: cannot load order by href', ['href' => $event->meta->href ?? null]);
                return;
            }

            $stateId = $this->extractStateId($order);
            if (!$stateId) return;

            $step = $this->router->resolve($stateId);
            if (!$step) {
                Log::orderCreate('CREATE: no step for state', [
                    'orderId' => $order->id ?? null,
                    'stateId' => $stateId,
                ]);
                return;
            }

            $step->run($ctx);

        } catch (\Throwable $e) {
            Log::orderCreate('CREATE: exception', [
                'orderId' => $orderId,
                'err'     => $e->getMessage(),
            ]);
        } finally {
            $this->releaseOrderLock($orderId);
        }
    }

    private function extractStateId(object $order): ?string
    {
      $href = $order->state->meta->href ?? null;
      if (!$href || !is_string($href)) return null;
      return basename($href);
    }

    private function lockKey(string $orderId): string
    {
        // ✅ общий ключ для CREATE + UPDATE
        return 'ms_lock_customerorder_' . $orderId;
    }

    private function acquireOrderLock(string $orderId, int $ttlSeconds = 60): bool
    {
        if (!isset(Yii::$app->cache)) return true; // если кеша нет — не блокируем

        $key = $this->lockKey($orderId);

        // add() вернет false, если ключ уже существует
        return Yii::$app->cache->add($key, 1, $ttlSeconds);
    }

    private function releaseOrderLock(string $orderId): void
    {
        if (!isset(Yii::$app->cache)) return;
        Yii::$app->cache->delete($this->lockKey($orderId));
    }
}
