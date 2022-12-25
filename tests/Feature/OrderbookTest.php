<?php

namespace Tests\Feature;

use Tests\TestCase;

class OrderbookTest extends TestCase
{
    /**
     * @return void
     */
    public function test_get_orderbook()
    {
        $response = $this->postJson('/api/orderbook/get', [
            'pair' => 'ETH_XRP',
            'amount' => 10,
        ]);

        $response->assertStatus(200);
    }
}
