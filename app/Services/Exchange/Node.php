<?php

namespace App\Services\Exchange;

class Node
{
    /** @var Edge[] */
    public array $edges = [];

    public function __construct(
        public readonly string $currency,
    ) {}

    /**
     * Добавить ребро
     *
     * @param Edge $edge
     * @return void
     */
    public function addEdge(Edge $edge): void
    {
        $this->edges[$edge->to->currency] = $edge;
    }
}
