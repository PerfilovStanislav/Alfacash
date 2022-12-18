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
    public function __construct(
        private readonly Exchange      $exchange,
        private readonly AsyncExchange $asyncExchange,
        private readonly string        $inCurrency,
        private readonly string        $outCurrency,
        private float                  $inVolume,
    ) {}

    /** валюты, по которым есть стаканы. ограничиваем этим списком, чтобы грузилось немного быстрее */
    private const AVAILABLE_QUOTES = ['BTC', 'USDT', 'BUSD', 'RUB', 'TRY', 'EUR', 'GBP', 'BIDR', 'AUD', 'NGN', 'BRL'];

    /** @var array */
    private array $markets;

    /** @var array[] */
    private array $orders;

    /** @var string[] */
    private array $intersectedQuotes;

    /** @var float */
    private float $outVolume = 0.0;

    /** @var array */
    private array $result = [];

    /**
     * @return array
     * @throws \Recoil\Exception\PanicException
     * @throws \ccxt\NotSupported
     */
    public function call(): array
    {
        $this->markets = $this->exchange->load_markets();

        $this->setIntersectedQuotes();
        $this->getOrders();

        while ($this->inVolume > 0) {
            $quote = $this->getQuoteWithBestRatio();
            if ($quote === '') {
                break;
            }

            $inPair  = self::pair($this->inCurrency, $quote);
            $outPair = self::pair($this->outCurrency, $quote);

            $inOrder  = $this->getOrder($inPair);
            $outOrder = $this->getOrder($outPair);

            $maxAvailableVolume = min($inOrder->volume, $this->inVolume);

            $inSum = $inOrder->price * $maxAvailableVolume * $this->getReminder($inPair);
            $outSum = $outOrder->price * $outOrder->volume;
            $minSum = min($inSum, $outSum);

            $inVolume = $maxAvailableVolume * ($minSum / $inSum);
            $this->inVolume -= $inVolume;
            $this->changeOrdersVolume($inPair, -$inVolume);
            $this->changeResultVolume($quote, 'in', $inVolume);

            $outVolume = $minSum / $outOrder->price;
            $this->outVolume += $outVolume;
            $this->changeOrdersVolume($outPair, -$outVolume);
            $this->changeResultVolume($quote, 'out', $outVolume * $this->getReminder($outPair));

            $this->removeFilledOrder($inPair);
            $this->removeFilledOrder($outPair);
        }

        $this->result += [
            'remind_volume'   => $this->inVolume,
            'out_volume'     => $this->outVolume,
        ];

        return $this->result;
    }

    /**
     * Получаем пересечения валют
     *
     * @return void
     */
    private function setIntersectedQuotes(): void
    {
        $inQuotes = $this->getQuotes($this->inCurrency);
        $outQuotes = $this->getQuotes($this->outCurrency);
        $this->intersectedQuotes = array_values(array_intersect($inQuotes, $outQuotes, self::AVAILABLE_QUOTES));
    }

    /**
     * @param string $direction
     * @return array
     */
    private function getQuotes(string $direction): array
    {
        $arr = array_filter(
            $this->markets,
            static fn(array $arr): bool => $arr['base'] === $direction
        );

        return array_column($arr, 'quote');
    }

    /**
     * Получаем стаканы
     *
     * @return void
     * @throws \Recoil\Exception\PanicException
     * @throws \ccxt\NotSupported
     */
    private function getOrders(): void
    {
        $arrPairs = [
            'bids' => $this->getPairs($this->inCurrency),
            'asks' => $this->getPairs($this->outCurrency),
        ];
        AsyncExchange::execute_and_run(function() use ($arrPairs) {
            $yields = [];
            foreach ($arrPairs as $direction => $pairs) {
                foreach ($pairs as $pair) {
                    $yields[] = $this->fetchOrderBooks($pair, $direction);
                }
            }
            yield $yields;
        });
    }

    /**
     * Получаем всевозможные пары для пересечений
     *
     * @param string $base
     * @return string[]
     */
    private function getPairs(string $base): array
    {
        return array_map(
            static fn(string $quote): string => self::pair($base, $quote),
            $this->intersectedQuotes
        );
    }

    /**
     * @param string $pair
     * @param string $direction
     * @return Generator
     */
    private function fetchOrderBooks(string $pair, string $direction): Generator {
        $this->orders[$pair] = (yield $this->asyncExchange->fetch_order_book($pair))[$direction];
    }

    /**
     * получаем валюту c лучшим соотношением купли/продажи
     *
     * @return string
     */
    private function getQuoteWithBestRatio(): string
    {
        $maxRatio = 0;
        $selectedQuote = '';
        foreach ($this->intersectedQuotes as $quote) {
            $inPair  = self::pair($this->inCurrency, $quote);
            $outPair = self::pair($this->outCurrency, $quote);

            $ratio = $this->getRatio($inPair, $outPair);
            if ($ratio > $maxRatio) {
                $maxRatio = $ratio;
                $selectedQuote = $quote;
            }
        }

        return $selectedQuote;
    }

    /**
     * @param string $base
     * @param string $quote
     * @return string
     */
    private static function pair(string $base, string $quote): string
    {
        return "$base/$quote";
    }

    /**
     * получаем соотношение двух самых прибыльных заявок
     *
     * @param string $inPair
     * @param string $outPair
     * @return float
     */
    private function getRatio(string $inPair, string $outPair): float
    {
        $sum = $this->getOrder($outPair)->price * $this->getReminder($outPair);
        if ($sum === 0.0) {
            return -1;
        }

        return $this->getOrder($inPair)->price * $this->getReminder($inPair) / $sum;
    }

    /**
     * остаток после комиссии
     *
     * @param string $pair
     * @return float
     */
    private function getReminder(string $pair): float
    {
        return 1 - $this->getFee($pair);
    }

    /**
     * комиссия за пару
     *
     * @param string $pair
     * @return float
     */
    private function getFee(string $pair): float
    {
        return $this->markets[$pair]['taker'];
    }

    /**
     * получить первый ордер
     *
     * @param string $pair
     * @return Order
     */
    private function getOrder(string $pair): Order
    {
        return new Order(...($this->orders[$pair][0] ?? [0, 0]));
    }

    /**
     * изменяем объём ближайшего ордера
     *
     * @param string $pair
     * @param float $volume
     * @return void
     */
    private function changeOrdersVolume(string $pair, float $volume): void
    {
        $this->orders[$pair][0][1] += $volume;
    }

    /**
     * запоминаем изменения объёмов для вывода
     *
     * @param string $quote
     * @param string $direction
     * @param float $volume
     * @return void
     */
    private function changeResultVolume(string $quote, string $direction, float $volume): void
    {
        $this->result[$quote][$direction] = ($this->result[$quote][$direction] ?? 0) + $volume;
    }

    /**
     * Удаляем заполненный ордер
     *
     * @param string $pair
     * @return void
     */
    private function removeFilledOrder(string $pair): void
    {
        if ($this->getOrder($pair)->isEmpty()) {
            array_shift($this->orders[$pair]);
        }
    }
}
