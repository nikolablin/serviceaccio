<?php
namespace app\services\handlers;

use Yii;
use app\services\support\Context;
use app\services\support\StepRouter;
use app\services\support\Log;

class FactureOutUpdateHandlerV2
{
    private StepRouter $router;

    public function __construct()
    {
        $this->router = new StepRouter(
            require __DIR__ . '/../support/factureout_steps_map.php'
        );
    }

    public function handle(object $event): void
    {
        if (($event->meta->type ?? null) !== 'factureout') {
            return;
        }
        if (($event->action ?? null) !== 'UPDATE') {
            return;
        }

        $factureoutId = basename((string)($event->meta->href ?? ''));
        if ($factureoutId === '') return;

        if (!$this->acquireFactureoutLock((string)$factureoutId, 5)) {
            Log::factureoutUpdate('UPDATE: skipped by cache lock (duplicate webhook / parallel run)', [ 'factureoutId' => (string)$factureoutId, ]);
            return;
        }

        try {
            $ctx = new Context($event);

            // гарантируем id (на случай если detectEntityIds не сработал)
            if (!$ctx->msFactureoutId) {
                $ctx->msFactureoutId = $factureoutId;
            }

            $factureout = $ctx->getFactureout();

            if (!$factureout) {
                Log::factureoutUpdate('UPDATE: cannot load factureout by href', [  'href' => $event->meta->href ?? null, 'id'   => $factureoutId ]);
                return;
            }


            $stateId = $this->extractStateId($factureout);
            if (!$stateId) return;

            $step = $this->router->resolve($stateId);

            if (!$step) {
                Log::factureoutUpdate('UPDATE: no step for state', [ 'factureoutId' => $factureout->id ?? null, 'stateId' => $stateId, ]);
                return;
            }

            $step->run($ctx);

        } catch (\Throwable $e) {
            Log::factureoutUpdate('UPDATE: exception', [ 'factureoutId' => $factureoutId, 'err' => $e->getMessage(), ]);
        } finally {
            $this->releaseFactureoutLock((string)$factureoutId);
        }

    }

    private function extractStateId(object $factureout): ?string
    {
      $href = $factureout->state->meta->href ?? null;
      if (!$href || !is_string($href)) return null;
      return basename($href);
    }

    private function lockKey(string $factureoutId): string
    {
        return 'ms_lock_factureout_' . $factureoutId;
    }

    private function acquireFactureoutLock(string $factureoutId, int $ttlSeconds = 60): bool
    {
        if (!isset(Yii::$app->cache)) return true;
        return Yii::$app->cache->add($this->lockKey($factureoutId), 1, $ttlSeconds);
    }

    private function releaseFactureoutLock(string $factureoutId): void
    {
        if (!isset(Yii::$app->cache)) return;
        Yii::$app->cache->delete($this->lockKey($factureoutId));
    }
}
