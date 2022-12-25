<?php

namespace App\Services\Exchange;

class Path implements \Stringable
{
    public function __construct(
        private readonly array $currencies,
    ) {}

    /** @var Edge[] */
    public array $edges;

    /**
     * @param Edge $edge
     * @return void
     */
    public function addEdge(Edge $edge): void
    {
        $this->edges[] = $edge;
    }

    /**
     * Получить соотношение по полному пути
     *
     * @return float
     */
    public function getRatio(): float
    {
        $ratio = 1;
        foreach ($this->edges as $edge) {
            $ratio *= $edge->getRatio();
        }

        return $ratio;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return implode('->', $this->currencies);
    }


}
