<?php

use Illuminate\Support\Facades\Route as R;
use App\Http\Controllers\Api\{
    OrderBookController,
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

R::group(['prefix' => '/orderbook'], function () {
    R::post('/get', [OrderBookController::class, 'get']);
});
