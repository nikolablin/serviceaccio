<?php
namespace app\services\support;

use Yii;
use app\models\MoyskladV2;
use app\services\OrdersConfigResolverV2;
use app\models\OrdersConfigTable;

class Context
{
    public object $event;
    public string $action;

    public ?object $msOrder = null;
    public ?object $msDemand = null;
    private ?object $invoiceOut = null;
    private ?object $paymentIn = null;
    private ?object $cashIn = null;
    private ?object $salesreturn = null;
    private ?object $factureOut = null;

    public ?string $msOrderId = null;
    public ?string $msDemandId = null;
    public ?string $msSalesreturnId = null;
    public ?string $msFactureoutId = null;


    private ?MoyskladV2 $ms = null;
    private ?OrdersConfigTable $config = null;

    public function __construct(object $event)
    {
        $this->event = $event;
        $this->action = (string)($event->action ?? '');

        // попробуем заранее вытащить entity id из события
        $this->detectEntityIds();
    }

    public function ms(): MoyskladV2
    {
        if ($this->ms === null) {
            $this->ms = new MoyskladV2();
        }
        return $this->ms;
    }

    /** Всегда вернёт объект заказа из МС (если возможно) */
    public function getOrder(): ?object
    {
        if ($this->msOrder !== null) {
            return $this->msOrder;
        }


        // 1) если у нас событие по заказу — id есть
        if ($this->msOrderId) {
            $this->msOrder = $this->ms()->getCustomerOrder($this->msOrderId);
            return $this->msOrder;
        }

        // 2) если событие по отгрузке — попробуем найти заказ по отгрузке
        $demand = $this->getDemand();
        if ($demand) {
            $orderId = $this->ms()->extractCustomerOrderIdFromDemand($demand);
            if ($orderId) {
                $this->msOrderId = $orderId;
                $this->msOrder = $this->ms()->getCustomerOrder($orderId);
            }
        }

        return $this->msOrder;
    }

    /** Всегда вернёт объект отгрузки из МС (если возможно) */
    public function getDemand(): ?object
    {

        if ($this->msDemand !== null) {
            return $this->msDemand;
        }

        // 1) если у нас событие по отгрузке — id есть
        if ($this->msDemandId) {
            $this->msDemand = $this->ms()->getDemand($this->msDemandId);
            if (
                isset($this->msDemand->positions)
                && (!isset($this->msDemand->positions->rows) || !is_array($this->msDemand->positions->rows))
                && isset($this->msDemand->positions->meta->href)
            ) {
                $href = (string)$this->msDemand->positions->meta->href;
                $href .= (str_contains($href, '?') ? '&' : '?') . 'expand=' . rawurlencode('assortment');

                $pos = $this->ms()->getHrefData($href);

                if ($pos && isset($pos->rows) && is_array($pos->rows)) {
                    $this->msDemand->positions->rows = $pos->rows;
                }

            }
            return $this->msDemand;
        }

        // 2) если событие по заказу — берём отгрузку из $order->demands
        $order = $this->getOrder();

        if ($order && !empty($order->id)) {

            $demands = $order->demands ?? null;

            if (is_array($demands) && !empty($demands[0]->meta->href)) {
                $href = (string)$demands[0]->meta->href;

                // грузим сам demand по href + expand
                $expand = Yii::$app->params['moyskladv2']['demands']['expand'] ?? '';
                if ($expand) {
                    $href .= (str_contains($href, '?') ? '&' : '?') . 'expand=' . rawurlencode($expand);
                }

                $this->msDemand = $this->ms()->getHrefData($href);

                if ($this->msDemand && !empty($this->msDemand->id)) {
                    $this->msDemandId = (string)$this->msDemand->id;

                    if (
                        isset($this->msDemand->positions)
                        && (!isset($this->msDemand->positions->rows) || !is_array($this->msDemand->positions->rows))
                        && isset($this->msDemand->positions->meta->href)
                    ) {
                        $href = (string)$this->msDemand->positions->meta->href;
                        $href .= (str_contains($href, '?') ? '&' : '?') . 'expand=' . rawurlencode('assortment');

                        $pos = $this->ms()->getHrefData($href);

                        if ($pos && isset($pos->rows) && is_array($pos->rows)) {
                            $this->msDemand->positions->rows = $pos->rows;
                        }
                    }
                }

                return $this->msDemand;
            }

            return null;
        }

        return null;
    }

    public function getDemandFromSalesreturn(?object $salesreturn = null): ?object
    {
        $salesreturn = $salesreturn ?: $this->getSalesreturn();
        if (!$salesreturn) return null;

        $href = $salesreturn->demand->meta->href ?? null;
        if (!$href || !is_string($href)) return null;

        // expand для demand (как у тебя в params)
        $expand = Yii::$app->params['moyskladv2']['demands']['expand'] ?? '';
        if ($expand) {
            $href .= (str_contains($href, '?') ? '&' : '?') . 'expand=' . rawurlencode($expand);
        }

        $demand = $this->ms()->getHrefData($href);
        if (!$demand || empty($demand->id)) return null;

        // запомним в контексте, чтобы дальше Context::getDemand() возвращал уже это
        $this->msDemand   = $demand;
        $this->msDemandId = (string)$demand->id;

        // если positions не развернулись — дотянем rows с assortment
        if (
            isset($this->msDemand->positions)
            && (!isset($this->msDemand->positions->rows) || !is_array($this->msDemand->positions->rows))
            && isset($this->msDemand->positions->meta->href)
        ) {
            $posHref = (string)$this->msDemand->positions->meta->href;
            $posHref .= (str_contains($posHref, '?') ? '&' : '?') . 'expand=' . rawurlencode('assortment');

            $pos = $this->ms()->getHrefData($posHref);
            if ($pos && isset($pos->rows) && is_array($pos->rows)) {
                $this->msDemand->positions->rows = $pos->rows;
            }
        }

        return $this->msDemand;
    }

    public function getDemandFromFactureout(?object $factureOut = null): ?object
    {
        $factureOut = $factureOut ?: $this->getFactureout();
        if (!$factureOut) return null;

        $href = $factureOut->demands[0]->meta->href ?? null;
        if (!$href || !is_string($href)) return null;

        // expand для demand (как у тебя в params)
        $expand = Yii::$app->params['moyskladv2']['demands']['expand'] ?? '';
        if ($expand) {
            $href .= (str_contains($href, '?') ? '&' : '?') . 'expand=' . rawurlencode($expand);
        }

        $demand = $this->ms()->getHrefData($href);
        if (!$demand || empty($demand->id)) return null;

        // запомним в контексте, чтобы дальше Context::getDemand() возвращал уже это
        $this->msDemand   = $demand;
        $this->msDemandId = (string)$demand->id;

        // если positions не развернулись — дотянем rows с assortment
        if (
            isset($this->msDemand->positions)
            && (!isset($this->msDemand->positions->rows) || !is_array($this->msDemand->positions->rows))
            && isset($this->msDemand->positions->meta->href)
        ) {
            $posHref = (string)$this->msDemand->positions->meta->href;
            $posHref .= (str_contains($posHref, '?') ? '&' : '?') . 'expand=' . rawurlencode('assortment');

            $pos = $this->ms()->getHrefData($posHref);
            if ($pos && isset($pos->rows) && is_array($pos->rows)) {
                $this->msDemand->positions->rows = $pos->rows;
            }
        }

        return $this->msDemand;
    }

    public function getInvoice(): ?object
    {
        if ($this->invoiceOut) {
            return $this->invoiceOut;
        }

        $order = $this->getOrder();
        if (!$order) return null;

        // 1) invoice href из заказа
        $href = null;
        if (!empty($order->invoicesOut) && is_array($order->invoicesOut)) {
            $href = $order->invoicesOut[0]->meta->href ?? null;
        }

        if ($href && is_string($href)) {
            $inv = $this->ms()->getHrefData($href);
            if ($inv && !empty($inv->id)) {
                $this->invoiceOut = $inv;
                return $this->invoiceOut;
            }
        }

        return null;
    }

    public function getFactureout(): ?object
    {
        if ($this->factureOut !== null) {
          return $this->factureOut;
        }

        if ($this->msFactureoutId) {
            $this->factureOut = $this->ms()->getHrefData(
                "https://api.moysklad.ru/api/remap/1.2/entity/factureout/" . $this->msFactureoutId
            );
            return $this->factureOut;
        }

        $demand = $this->getDemand();
        if (!$demand) return null;

        $href = null;
        if (isset($demand->factureOut) && is_object($demand->factureOut)) {
            $href = $demand->factureOut->meta->href ?? null;
        }

        if ($href && is_string($href)) {
            $sr = $this->ms()->getHrefData($href);
            if ($sr && !empty($sr->id)) {
                $this->factureOut = $sr;
                $this->msFactureoutId = (string)$sr->id;
                return $this->factureOut;
            }
        }

        return null;
    }

    public function getSalesreturn(): ?object
    {
        if ($this->salesreturn !== null) {
            return $this->salesreturn;
        }

        // 1) Если событие по salesreturn — грузим напрямую по id
        if ($this->msSalesreturnId) {
            $this->salesreturn = $this->ms()->getHrefData(
                "https://api.moysklad.ru/api/remap/1.2/entity/salesreturn/" . $this->msSalesreturnId
            );
            return $this->salesreturn;
        }

        // 2) Фоллбек: если есть demand — попробуем взять returns[0] из demand
        $demand = $this->getDemand();
        if (!$demand) return null;

        $href = null;
        if (!empty($demand->returns) && is_array($demand->returns)) {
            $href = $demand->returns[0]->meta->href ?? null;
        }

        if ($href && is_string($href)) {
            $sr = $this->ms()->getHrefData($href);
            if ($sr && !empty($sr->id)) {
                $this->salesreturn = $sr;
                $this->msSalesreturnId = (string)$sr->id;
                return $this->salesreturn;
            }
        }

        return null;
    }

    public function getPaymentIn(): ?object
    {
        if ($this->paymentIn !== null) {
            return $this->paymentIn;
        }

        $demand = $this->getDemand();
        if (!$demand) { return null; }

        // если МС прислал linked docs прямо в order (зависит от expand)
        if (!empty($demand->payments) && is_array($demand->payments)) {
            foreach ($demand->payments as $p) {
                if (($p->meta->type ?? null) === 'paymentin') {
                    $this->paymentIn = $this->ms()->getHrefData((string)$p->meta->href);
                    return $this->paymentIn;
                }
            }
        }

        return null;
    }

    public function getCashIn(): ?object
    {
        if ($this->cashIn !== null) {
            return $this->cashIn;
        }

        $demand = $this->getDemand();
        if (!$demand) { return null; }

        if (!empty($demand->payments) && is_array($demand->payments)) {
            foreach ($demand->payments as $p) {
                if (($p->meta->type ?? null) === 'cashin') {
                    $this->cashIn = $this->ms()->getHrefData((string)$p->meta->href);
                    return $this->cashIn;
                }
            }
        }

        return null;
    }

    public function getConfig(): ?OrdersConfigTable
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $order = $this->getOrder();
        if (!$order) {
            return null;
        }

        $resolver = new OrdersConfigResolverV2();
        $this->config = $resolver->resolve($order, $this->ms());

        return $this->config;
    }

    private function detectEntityIds(): void
    {
        $type = (string)($this->event->meta->type ?? '');

        $href = $this->event->meta->href ?? null;
        if (!$href) $href = $this->event->entity->meta->href ?? null;

        $entityId = (is_string($href) && $href) ? basename($href) : null;

        if ($type === 'customerorder') $this->msOrderId = $entityId;
        if ($type === 'demand')        $this->msDemandId = $entityId;
        if ($type === 'salesreturn')        $this->msSalesreturnId = $entityId;
    }
}
