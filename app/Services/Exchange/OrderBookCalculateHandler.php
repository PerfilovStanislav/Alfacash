<?php

namespace App\Services\Exchange;

use ccxt\Exchange;
use ccxt\async\Exchange as AsyncExchange;
use Generator;

/**
 * Класс для нахождения самого выгодного обмена валюты
 */
class OrderBookCalculateHandler
{
    /** Список ликвидных валют */
    private const AVAILABLE_CURRENCIES = ['BTC', 'ETH', 'USDT', 'USDC', 'BNB', 'BUSD', 'XRP', 'LTC', 'TRX', 'DOT'];

    private Graph $graph;

    public function __construct(
        private readonly Exchange      $exchange,
        private readonly AsyncExchange $asyncExchange,
        private readonly string        $inCurrency,
        private readonly string        $outCurrency,
        private float                  $inVolume,
    ) {
        $this->graph = new Graph();
    }

    /** @var array */
    private array $markets;

    /** @var Path[] */
    private array $foundPaths;

    /** @var array */
    private array $orders;

    /** @var Path[] */
    private array $result;

    /**
     * @return array[]|\array[][]
     */
    public function call(): array
    {
        $this->markets = $this->exchange->load_markets();

        $this->fillGraph();
        $this->findPaths($this->inCurrency, $this->outCurrency, [$this->inCurrency]);
        $this->sortFoundPathsByLength();

        $this->getOrderBooks(
            $this->getPairListForLoadOrders()
        );

        $this->fillEdges();
        $this->calculate();

        $x = $this->showResult();
        return dd($x, json_encode($x));
    }

    /**
     * Заполняем граф вершинами и рёбрами
     *
     * @return void
     */
    private function fillGraph(): void
    {
        foreach ($this->markets as $market) {
            if ($market['active'] === false) {
                continue;
            }
            if (!in_array($market['base'], self::AVAILABLE_CURRENCIES)) {
                continue;
            }
            if (!in_array($market['quote'], self::AVAILABLE_CURRENCIES)) {
                continue;
            }
            $this->addNodeToGraph($market);
        }
    }

    /**
     * добавить ноду в граф
     *
     * @param array $market
     * @return void
     */
    private function addNodeToGraph(array $market): void
    {
        $baseNode  = $this->graph->getNodeInstance($market['base']);
        $quoteNode = $this->graph->getNodeInstance($market['quote']);

        $reminder = 1 - $market['taker'];

        $baseEdge  = new Edge($baseNode, $quoteNode, $reminder);
        $quoteEdge = new Edge($quoteNode, $baseNode, $reminder, false);

        $baseNode->addEdge($baseEdge);
        $quoteNode->addEdge($quoteEdge);
    }

    /**
     * Находим все возможные пути от одной валюты до другой
     *
     * @param string $currentCurrency
     * @param string $endCurrency
     * @param array $path
     * @return void
     */
    private function findPaths(string $currentCurrency, string $endCurrency, array $path = []): void
    {
        if ($currentCurrency === $endCurrency) {
            $this->foundPaths[] = $this->createPath($path);
            return;
        }

        $currentNode = $this->graph->getNode($currentCurrency);
        foreach ($currentNode->edges as $edge) {
            $toCurrency = $edge->to->currency;
            if (in_array($toCurrency, $path)) {
                continue;
            }
            $this->findPaths($toCurrency, $endCurrency, [...$path, $toCurrency]);
        }
    }

    /**
     * сортируем найденные пути по кол-ву вершин
     *
     * @return void
     */
    private function sortFoundPathsByLength(): void
    {
        usort(
            $this->foundPaths,
            static fn(Path $a, Path $b) => count($a->edges) <=> count($b->edges)
        );
    }

    /**
     * Вносим данные стакана в рёбра
     *
     * @return void
     */
    private function fillEdges(): void
    {
        foreach ($this->graph->edges as $edges) {
            foreach ($edges as $edge) {
                $orders = $this->orders[$edge->pair][$edge->forward ? 'bids' : 'asks'];
                $edge->setData($orders);
            }
        }
    }

    /**
     * Получаем список пар, которые необходимо загрузить
     *
     * @return string[]
     */
    private function getPairListForLoadOrders(): array
    {
        $pairs = [];
        foreach ($this->foundPaths as $path) {
            foreach ($path->edges as $edge) {
                $pairs[$edge->pair] = true;
            }
        }

        return array_keys($pairs);
    }

    /**
     * @return void
     */
    private function calculate(): void
    {
        while (true) {
            $maxRatio = 0;
            $bestPath = null;
            foreach ($this->foundPaths as $path) {
                $ratio = $path->getRatio();
                if ($ratio > $maxRatio) {
                    $maxRatio = $ratio;
                    $bestPath = $path;
                }
            }
            if ($maxRatio === 0 || $this->exchange($bestPath) === false) {
                break;
            }

            if ($this->inVolume === 0.0) {
                break;
            }
        }
    }

    /**
     * Вычисляем на сколько мы можем заполнить стакан и совершаем обмен из одной валюты в другую
     *
     * @param Path $path
     * @return bool
     */
    private function exchange(Path $path): bool
    {
        $outVolume = $this->inVolume;
        $fillingPercent = 1.0;
        foreach ($path->edges as $edge) {
            [$percent, $outVolume] = $edge->calculateVolumeFilling($outVolume);
            $fillingPercent *= $percent;
        }
        if ($fillingPercent === 0.0) {
            return false;
        }

        $volume = $this->inVolume * $fillingPercent;
        foreach ($path->edges as $edge) {
            $volume = $edge->exchange($volume);
        }
        $this->inVolume *= (1 - $fillingPercent);

        $this->result[(string)$path] = $path;

        return true;
    }

    /**
     * Создать путь
     *
     * @param string[] $currencies
     * @return Path
     */
    private function createPath(array $currencies): Path
    {
        $path = new Path($currencies);

        $cntPairs = count($currencies) - 1;
        for($i = 0; $i < $cntPairs; $i++) {
            $from = $currencies[$i];
            $to   = $currencies[$i+1];
            $edge = $this->graph->edges[$from][$to] ??= $this->graph->getEdge($from, $to);
            $path->addEdge($edge);
        }

        return $path;
    }

    /**
     * Получаем стаканы из шлюза
     *
     * @param string[] $pairs
     * @return void
     */
    private function getOrderBooks(array $pairs): void
    {
        AsyncExchange::execute_and_run(function() use ($pairs) {
            $yields = [];
            foreach ($pairs as $pair) {
                $yields[] = $this->fetchOrderBooks($pair);
            }
            yield $yields;
        });
    }

    /**
     * @param string $pair
     * @return Generator
     */
    private function fetchOrderBooks(string $pair): Generator {
        $this->orders[$pair] = yield $this->asyncExchange->fetch_order_book($pair);
    }

    /**
     * Показываем результат
     *
     * @return array[]|\array[][]
     */
    private function showResult(): array
    {
        $res = [];
        $in = $out = 0.0;
        foreach ($this->result as $str => $path) {
            $res[$str] = $this->showPath($path);
            $in  += $res[$str]['total']['in'];
            $out += $res[$str]['total']['out'];
        }

        return [
            'total' => [
                    'in'    => $in,
                    'out'   => $out,
                    'rate'  => $out / $in,
                ] + ['details' => $res]
        ];
    }

    /**
     * @param Path $path
     * @return array|array[]
     */
    private function showPath(Path $path): array
    {
        $inVolume = $path->edges[0]->inVolume;
        $outVolume = last($path->edges)->outVolume;

        $res = [
            'total' => [
                'in'    => $inVolume,
                'out'   => $outVolume,
                'rate'  => $outVolume / $inVolume,
            ]
        ];

        foreach ($path->edges as $edge) {
            $res['details'][(string)$edge] = [
                'in'    => $edge->inVolume,
                'out'   => $edge->outVolume,
                'rate'  => $edge->outVolume / $edge->inVolume,
            ];
        }

        return $res;
    }
}
