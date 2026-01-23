<?php
namespace app\services\handlers;

use app\services\support\Context;
use app\services\support\StepRouter;
use app\services\support\Log;

class CustomerOrderUpdateHandlerV2
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
        if (($event->meta->type ?? null) !== 'customerorder') {
            return;
        }
        if (($event->action ?? null) !== 'UPDATE') {
            return;
        }

        $ctx = new Context($event);

        $order = $ctx->getOrder();

        if (!$order) {
          Log::orderUpdate('UPDATE: cannot load order by href', [
              'href' => $event->meta->href ?? null,
          ]);
          return;
        }

        $stateId = $this->extractStateId($order);

        if (!$stateId) {
            return;
        }

        $step = $this->router->resolve($stateId);

        if (!$step) {
           Log::orderUpdate('UPDATE: no step for state', [
               'orderId' => $order->id ?? null,
               'stateId' => $stateId,
           ]);
           return;
        }

        $step->run($ctx);
    }

    private function extractStateId(object $order): ?string
    {
      $href = $order->state->meta->href ?? null;
      if (!$href || !is_string($href)) return null;
      return basename($href);
    }
}
