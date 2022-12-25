<?php

namespace App\Services\Exchange;

class Edge implements \Stringable
{
    /** @var string */
    public string $pair;

    /** @var float[][] */
    private array $orders;

    private ?Order $order;

    /** @var float */
    public float $inVolume  = 0.0;

    /** @var float */
    public float $outVolume = 0.0;

    public function __construct(
        public readonly Node $from,
        public readonly Node $to,
        public readonly float $reminder,
        public readonly bool $forward = true,
    ) {
        $this->pair = $this->forward
            ? "{$this->from->currency}/{$this->to->currency}"
            : "{$this->to->currency}/{$this->from->currency}";
    }

    /**
     * @param array $orders
     * @return void
     */
    public function setData(array $orders): void
    {
        $this->orders = $orders;
    }

    /**
     * Получить соотношение
     *
     * @return float
     */
    public function getRatio(): float
    {
        $order = $this->getOrder();
        if ($order->isEmpty()) {
            return 0.0;
        }
        $ratio = $this->forward
            ? $order->price
            : 1 / $order->price;

        return $ratio * $this->reminder;
    }

    /**
     * Получить первый ордер из стакана
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order ??= new Order(...($this->orders[0] ?? [0, 0]));
    }

    /**
     * Рассчитываем на сколько мы можем заполнить стакан
     *
     * @param float $inVolume
     * @return array{float, float}
     */
    public function calculateVolumeFilling(float $inVolume): array
    {
        $order = $this->getOrder();

        $ratio  = min($inVolume, $order->volume * ($this->forward ? 1.0 : $order->price)) / $inVolume;
        $volume = min($inVolume / ($this->forward ? 1.0 : $order->price), $order->volume) * $this->reminder;

        return [$ratio, $volume];
    }

    /**
     * Совершаем обмен и возвращаем объём новой валюты
     *
     * @param float $inVolume
     * @return float
     */
    public function exchange(float $inVolume): float
    {
        $order = $this->getOrder();

        if ($this->forward) {
            $filledVolume = min($inVolume, $order->volume);
            $spent = $filledVolume;
            $got   = $filledVolume * $order->price * $this->reminder;
        } else {
            $filledVolume = min($inVolume / $order->price, $order->volume);
            $spent = $filledVolume * $order->price;
            $got   = $filledVolume * $this->reminder;
        }

        $this->reduceOrderVolume($filledVolume);
        $this->inVolume  += $spent;
        $this->outVolume += $got;

        return $got;
    }

    /**
     * Убираем конвертируем объём из стакана
     *
     * @param float $volume
     * @return void
     */
    private function reduceOrderVolume(float $volume): void
    {
        $remindedVolume = ($this->orders[0][1] -= $volume);
        $this->order->volume = $remindedVolume;

        if ($remindedVolume <= 0) {
            array_shift($this->orders);
            $this->order = null;
        }
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return "{$this->from->currency}->{$this->to->currency}";
    }
}
