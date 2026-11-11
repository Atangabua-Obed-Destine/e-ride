<?php

use Illuminate\Support\Facades\Route;
use Modules\ZoneManagement\Http\Controllers\Api\Driver\ZoneController;
use Modules\ZoneManagement\Http\Controllers\Api\SafetyController;

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

// Area pause and danger reports: the same calls for riders (customer/...) and drivers (driver/...).
foreach (['customer', 'driver'] as $side) {
    Route::group(['prefix' => $side . '/safety', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::get('meta', [SafetyController::class, 'meta']);
        Route::get('nearby', [SafetyController::class, 'nearby'])->middleware('throttle:60,1,safety-nearby');
        Route::post('report', [SafetyController::class, 'report'])->middleware('throttle:10,1,safety-report');
    });
}
