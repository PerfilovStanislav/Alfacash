<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\OrderBookRequest;
use App\Services\Exchange\OrderBookCalculateHandler;
use ccxt\async;
use ccxt\binance;

class OrderBookController
{
    public function get(OrderBookRequest $request): array
    {
        [$inCurrency, $outCurrency] = explode('_', $request->pair);
        $amount = $request->amount;

        $exchange = (new binance());
        $asyncExchange = (new async\binance());
        $handler = new OrderBookCalculateHandler($exchange, $asyncExchange, $inCurrency, $outCurrency, $amount);

        return $handler->call();
    }
}
