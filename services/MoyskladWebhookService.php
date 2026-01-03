<?php

namespace app\services;

class MoyskladWebhookService
{
    public function handle(object $payload): void
    {
        foreach ($payload->events as $event) {
            $type   = $event->meta->type ?? null;
            $action = $event->action ?? null;

            if ($type === 'customerorder' && $action === 'CREATE') {
                (new CustomerOrderCreateHandler())->handle($event);
                continue;
            }

            if ($type === 'salesreturn' && $action === 'CREATE') {
                (new SalesReturnCreateHandler())->handle($event);
                continue;
            }

            if ($type === 'customerorder' && $action === 'UPDATE') {
                (new CustomerOrderUpdateHandler())->handle($event);
                continue;
            }

            if ($type === 'salesreturn' && $action === 'UPDATE') {
                (new SalesReturnUpdateHandler())->handle($event);
                continue;
            }

            if ($type === 'demand' && $action === 'UPDATE') {
                (new DemandUpdateHandler())->handle($event);
                continue;
            }
        }
    }
}
