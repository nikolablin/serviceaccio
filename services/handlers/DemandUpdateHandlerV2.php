<?php
namespace app\services\handlers;

use Yii;
use app\services\support\Context;
use app\services\support\StepRouter;
use app\services\support\Log;

class DemandUpdateHandlerV2
{
    private StepRouter $router;

    public function __construct()
    {
        $this->router = new StepRouter(
          require __DIR__ . '/../support/demands_steps_map.php'
        );
    }

    public function handle(object $event): void
    {
        if (($event->meta->type ?? null) !== 'demand') {
            return;
        }
        if (($event->action ?? null) !== 'UPDATE') {
            return;
        }

        $ctx = new Context($event);


        $demand = $ctx->getDemand();

        if (!$demand) {
          Log::demandUpdate('UPDATE: cannot load order by href', [
              'href' => $event->meta->href ?? null,
          ]);
          return;
        }

        if (!$this->acquireDemandLock((string)$demand->id, 20)) {
            Log::demandUpdate('UPDATE: skipped by cache lock (duplicate webhook / parallel run)', [ 'demandId' => (string)$demand->id, ]);
            return;
        }

        try {
            $stateId = $this->extractStateId($demand);
            if (!$stateId) return;

            $step = $this->router->resolve($stateId);
            if (!$step) {
                Log::demandUpdate('UPDATE: no step for state', [ 'demandId' => $demand->id ?? null, 'stateId'  => $stateId, ]);
                return;
            }

            $step->run($ctx);
        } catch (\Throwable $e) {
            Log::demandUpdate('UPDATE: exception', [ 'demandId' => (string)$demand->id, 'err'      => $e->getMessage(), ]);
        } finally {
            $this->releaseDemandLock((string)$demand->id);
        }
    }

    private function extractStateId(object $order): ?string
    {
      $href = $order->state->meta->href ?? null;
      if (!$href || !is_string($href)) return null;
      return basename($href);
    }

    private function lockKey(string $demandId): string
    {
       return 'ms_lock_demand_' . $demandId;
    }

    private function acquireDemandLock(string $demandId, int $ttlSeconds = 60): bool
    {
       if (!isset(Yii::$app->cache)) return true;
       return Yii::$app->cache->add($this->lockKey($demandId), 1, $ttlSeconds);
    }

    private function releaseDemandLock(string $demandId): void
    {
       if (!isset(Yii::$app->cache)) return;
       Yii::$app->cache->delete($this->lockKey($demandId));
    }
}
