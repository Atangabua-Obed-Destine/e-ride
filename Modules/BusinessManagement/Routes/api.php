<?php

use Illuminate\Support\Facades\Route;


Route::controller(\Modules\BusinessManagement\Http\Controllers\Api\ConfigurationController::class)->group(function () {
    Route::get('/configurations', 'getConfiguration');
    Route::get('/get-external-configurations', 'getExternalConfiguration');
    Route::post('/store-configurations', 'updateConfiguration');
});

Route::group(['prefix' => 'location', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::controller(\Modules\BusinessManagement\Http\Controllers\Api\Customer\ConfigController::class)->group(function () {
        Route::post('save', 'userLastLocation');
    });
});

#new route
Route::group(['prefix' => 'customer'], function () {
    Route::controller(\Modules\BusinessManagement\Http\Controllers\Api\Customer\ConfigController::class)->group(function () {
        Route::get('configuration', 'configuration');
        Route::get('pages/{page_name}', 'pages');
        Route::group(['prefix' => 'config'], function () {
            Route::get('get-zone-id', 'getZone');
            Route::get('place-api-autocomplete', 'placeApiAutocomplete');
            Route::get('distance-api', 'distanceApi');
            Route::get('place-api-details', 'placeApiDetails');
            Route::get('geocode-api', 'geocodeApi');
            Route::post('get-routes', 'getRoutes');
            Route::get('get-payment-methods', 'getPaymentMethods');
            Route::get('cancellation-reason-list', 'cancellationReasonList');
            Route::get('parcel-cancellation-reason-list', 'parcelCancellationReasonList');
            Route::get('parcel-refund-reason-list', 'parcelRefundReasonList');
            Route::get('other-emergency-contact-list', 'otherEmergencyContactList');
            Route::get('safety-alert-reason-list', 'safetyAlertReasonList');
            Route::get('safety-precaution-list', 'safetyPrecautionList');
            Route::get('calculate-distance', 'calculateDistance');
        });
    });
});

Route::group(['prefix' => 'driver'], function () {
    Route::controller(\Modules\BusinessManagement\Http\Controllers\Api\Driver\ConfigController::class)->group(function () {
        Route::get('configuration', 'configuration');
        Route::group(['prefix' => 'config'], function () {
            // These config will found in Customer Config
            Route::get('get-zone-id', 'getZone');
            Route::get('place-api-autocomplete', 'placeApiAutocomplete');
            Route::get('distance-api', 'distanceApi');
            Route::get('place-api-details', 'placeApiDetails');
            Route::get('geocode-api', 'geocodeApi');
            Route::get('get-payment-methods', 'getPaymentMethods');
            Route::get('cancellation-reason-list', 'cancellationReasonList');
            Route::get('parcel-cancellation-reason-list', 'parcelCancellationReasonList');
            Route::get('predefined-question-answer-list', 'predefinedQuestionAnswerList');
            Route::get('other-emergency-contact-list', 'otherEmergencyContactList');
            Route::get('safety-alert-reason-list', 'safetyAlertReasonList');
            Route::get('safety-precaution-list', 'safetyPrecautionList');
        });
        Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
            Route::post('get-routes', 'getRoutes');
        });
    });
});
