<?php

use Illuminate\Support\Facades\Route;
use Modules\ZoneManagement\Http\Controllers\Api\Driver\ZoneController;

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

Route::group(['prefix' => 'driver'], function () {
    Route::group(['prefix' => 'zone', 'middleware' => ['auth:api', 'maintenance_mode']], function () {

        Route::controller(ZoneController::class)->group(function () {
            Route::get('/list', 'list');
        });
    });
});
