<?php
namespace app\services\handlers;

use Yii;
use app\services\support\Context;
use app\services\support\StepRouter;
use app\services\support\Log;

class SalesreturnUpdateHandlerV2
{
    private StepRouter $router;

    public function __construct()
    {
        $this->router = new StepRouter(
            require __DIR__ . '/../support/salesreturn_steps_map.php'
        );
    }

    public function handle(object $event): void
    {
        if (($event->meta->type ?? null) !== 'salesreturn') {
            return;
        }
        if (($event->action ?? null) !== 'UPDATE') {
            return;
        }

        $salesreturnId = basename((string)($event->meta->href ?? ''));
        if ($salesreturnId === '') return;

        if (!$this->acquireSalesreturnLock((string)$salesreturnId, 5)) {
            Log::salesreturnUpdate('UPDATE: skipped by cache lock (duplicate webhook / parallel run)', [ 'salesreturnId' => (string)$salesreturnId, ]);
            return;
        }

        try {
            $ctx = new Context($event);

            // гарантируем id (на случай если detectEntityIds не сработал)
            if (!$ctx->msSalesreturnId) {
                $ctx->msSalesreturnId = $salesreturnId;
            }

            $salesreturn = $ctx->getSalesreturn();

            if (!$salesreturn) {
                Log::salesreturnUpdate('UPDATE: cannot load salesreturn by href', [  'href' => $event->meta->href ?? null, 'id'   => $salesreturnId ]);
                return;
            }


            $stateId = $this->extractStateId($salesreturn);
            if (!$stateId) return;


            $step = $this->router->resolve($stateId);

            if (!$step) {
                Log::salesreturnUpdate('UPDATE: no step for state', [ 'salesreturnId' => $salesreturn->id ?? null, 'stateId' => $stateId, ]);
                return;
            }

            $step->run($ctx);


        } catch (\Throwable $e) {
            Log::salesreturnUpdate('UPDATE: exception', [ 'salesreturnId' => $salesreturnId, 'err' => $e->getMessage(), ]);
        } finally {
            $this->releaseSalesreturnLock((string)$salesreturnId);
        }

    }

    private function extractStateId(object $salesreturn): ?string
    {
      $href = $salesreturn->state->meta->href ?? null;
      if (!$href || !is_string($href)) return null;
      return basename($href);
    }

    private function lockKey(string $salesreturnId): string
    {
        return 'ms_lock_salesreturn_' . $salesreturnId;
    }

    private function acquireSalesreturnLock(string $salesreturnId, int $ttlSeconds = 60): bool
    {
        if (!isset(Yii::$app->cache)) return true;
        return Yii::$app->cache->add($this->lockKey($salesreturnId), 1, $ttlSeconds);
    }

    private function releaseSalesreturnLock(string $salesreturnId): void
    {
        if (!isset(Yii::$app->cache)) return;
        Yii::$app->cache->delete($this->lockKey($salesreturnId));
    }
}
