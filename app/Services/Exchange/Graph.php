<?php

namespace App\Services\Exchange;

class Graph
{
    /** @var Node[] */
    public array $nodes = [];

    /** @var Edge[][] */
    public array $edges = [];

    /**
     * Создать и запомнить инстанс ноды
     *
     * @param string $currency
     * @return Node
     */
    public function getNodeInstance(string $currency): Node
    {
        return $this->nodes[$currency] ??= new Node($currency);
    }

    /**
     * @param string $currency
     * @return Node
     */
    public function getNode(string $currency): Node
    {
        return $this->nodes[$currency];
    }

    /**
     * @param string $from
     * @param string $to
     * @return Edge
     */
    public function getEdge(string $from, string $to): Edge
    {
        return $this->getNode($from)->edges[$to];
    }
}
