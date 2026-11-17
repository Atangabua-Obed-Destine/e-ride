<?php

use Illuminate\Support\Facades\Route;
use Modules\AssistantManagement\Http\Controllers\Api\AssistantController;

Route::group(['prefix' => 'customer/assistant', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::get('help', [AssistantController::class, 'help']);
    Route::post('ask', [AssistantController::class, 'ask'])->middleware('throttle:12,1,assistant-ask');
    Route::post('{question}/feedback', [AssistantController::class, 'feedback'])->middleware('throttle:30,1,assistant-feedback');
});
