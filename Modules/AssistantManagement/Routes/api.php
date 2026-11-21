<?php

use Illuminate\Support\Facades\Route;
use Modules\AssistantManagement\Http\Controllers\Api\AssistantController;

Route::group(['prefix' => 'customer/assistant', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::get('help', [AssistantController::class, 'help']);
    Route::post('ask', [AssistantController::class, 'ask'])->middleware('throttle:12,1,assistant-ask');
    // A voice note for the driver, recorded while booking (ride or parcel).
    Route::post('voice-trip', [AssistantController::class, 'voiceTrip'])->middleware('throttle:10,1,assistant-voice');
    Route::post('{question}/feedback', [AssistantController::class, 'feedback'])->middleware('throttle:30,1,assistant-feedback');
});

// Driver app: the driver info sheets and Ask E-Ride for drivers.
Route::group(['prefix' => 'driver/assistant', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::get('help', [\Modules\AssistantManagement\Http\Controllers\Api\DriverAssistantController::class, 'help']);
    Route::post('ask', [\Modules\AssistantManagement\Http\Controllers\Api\DriverAssistantController::class, 'ask'])->middleware('throttle:12,1,assistant-ask');
    Route::post('{question}/feedback', [\Modules\AssistantManagement\Http\Controllers\Api\DriverAssistantController::class, 'feedback'])->middleware('throttle:30,1,assistant-feedback');
});
