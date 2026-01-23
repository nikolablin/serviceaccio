<?php
namespace app\services\steps;

use Yii;
use app\services\support\Context;
use app\services\support\StepInterface;

abstract class AbstractStep implements StepInterface
{
    final public function run(Context $ctx): void
    {
        $name = static::class;
        $meta = $this->meta($ctx);

        $this->log('START', $name, $meta);

        // защита от повторов (можно выключать)
        if ($this->isIdempotent() && $this->alreadyProcessed($ctx)) {
            $this->log('SKIP_ALREADY_PROCESSED', $name, $meta);
            return;
        }

        try {
            $this->before($ctx);
            $this->process($ctx);
            $this->after($ctx);

            if ($this->isIdempotent()) {
                $this->markProcessed($ctx);
            }

            $this->log('DONE', $name, $meta);
        } catch (\Throwable $e) {
            $this->log('FAIL', $name, $meta + [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            throw $e; // пусть handler решает, глотать или нет
        }
    }

    /** Основная логика шага */
    abstract protected function process(Context $ctx): void;

    /** Можно переопределить при необходимости */
    protected function before(Context $ctx): void {}
    protected function after(Context $ctx): void {}

    /** Включаем/выключаем идемпотентность */
    protected function isIdempotent(): bool { return true; }

    /**
     * Ключ идемпотентности.
     * По умолчанию: entityId + action + stateId + stepClass
     */
    protected function idempotencyKey(Context $ctx): string
    {
        $entityId = $this->extractEntityId($ctx) ?? 'unknown';
        $action   = (string)($ctx->event->action ?? '');
        $stateId  = $this->extractStateId($ctx) ?? 'nostate';

        return implode(':', [$entityId, $action, $stateId, static::class]);
    }

    protected function alreadyProcessed(Context $ctx): bool
    {
        $key = $this->idempotencyKey($ctx);

        // простой вариант: cache (если нет — можно заменить на таблицу)
        $cacheKey = 'step_done:' . md5($key);
        return (bool)Yii::$app->cache->get($cacheKey);
    }

    protected function markProcessed(Context $ctx, int $ttlSeconds = 3600): void
    {
        $key = $this->idempotencyKey($ctx);
        $cacheKey = 'step_done:' . md5($key);

        Yii::$app->cache->set($cacheKey, 1, $ttlSeconds);
    }

    protected function extractStateId(Context $ctx): ?string
    {
        $href = $ctx->event->state->meta->href ?? null;
        if (!$href) $href = $ctx->event->entity->state->meta->href ?? null;
        if (!$href || !is_string($href)) return null;

        return basename($href);
    }

    protected function extractEntityId(Context $ctx): ?string
    {
        // обычно id можно вытащить из meta->href
        $href = $ctx->event->meta->href ?? null;
        if (!$href) $href = $ctx->event->entity->meta->href ?? null;
        if (!$href || !is_string($href)) return null;

        return basename($href);
    }

    protected function meta(Context $ctx): array
    {
        return [
            'entityType' => (string)($ctx->event->meta->type ?? ''),
            'action'     => (string)($ctx->event->action ?? ''),
            'entityId'   => $this->extractEntityId($ctx),
            'stateId'    => $this->extractStateId($ctx),
        ];
    }

    protected function log(string $tag, string $step, array $context = []): void
    {
        // пока без философии: просто Yii::info
        Yii::info($tag . ' ' . $step . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE), 'moyskladv2');
    }
}
