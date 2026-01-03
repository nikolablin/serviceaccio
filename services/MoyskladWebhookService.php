<?php

namespace app\services;

use Yii;

class MoyskladWebhookService
{
    public function handle(object $payload): void
    {
        if (empty($payload->events) || !is_array($payload->events)) {
            return;
        }

        $map = [
            'customerorder:CREATE' => CustomerOrderCreateHandler::class,
            'customerorder:UPDATE' => CustomerOrderUpdateHandler::class,
            'salesreturn:CREATE'   => SalesReturnCreateHandler::class,
            'salesreturn:UPDATE'   => SalesReturnUpdateHandler::class,
            'demand:UPDATE'        => DemandUpdateHandler::class,
        ];

        foreach ($payload->events as $event) {
            $type   = $event->meta->type ?? null;
            $action = $event->action ?? null;

            if (!$type || !$action) {
                continue;
            }

            $key = $type . ':' . $action;

            if (isset($map[$key])) {
                $handlerClass = $map[$key];
                (new $handlerClass())->handle($event);
            } else {
                // логируем неизвестные события
                file_put_contents(
                    __DIR__ . '/../logs/ms_service/webhook_unknown.txt',
                    date('Y-m-d H:i:s') . " unknown key={$key} href=" . ($event->meta->href ?? '') . "\n",
                    FILE_APPEND
                );
            }
        }
    }
}
