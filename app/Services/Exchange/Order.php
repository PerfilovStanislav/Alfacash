<?php

namespace App\Services\Exchange;

class Order
{
    public function __construct(
        public float $price,
        public float $volume,
    ) {}

    public function isEmpty(): bool
    {
        return $this->volume === 0.0;
    }
}
