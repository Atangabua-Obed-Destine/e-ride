<?php

use Illuminate\Support\Facades\Route;
use Modules\ZoneManagement\Http\Controllers\Web\Admin\SafetyAdminController;
use Modules\ZoneManagement\Http\Controllers\Web\Admin\ZoneController;

//New Route Mamun
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'zone', 'as' => 'zone.'], function () {
        Route::controller(ZoneController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::put('update/{id}', 'update')->name('update');
            Route::delete('delete/{id}', 'destroy')->name('delete');
            Route::get('status', 'status')->name('status');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('restore/{id}', 'restore')->name('restore');
            Route::delete('permanent-delete/{id}', 'permanentDelete')->name('permanent-delete');
            Route::get('get-zones', 'getZones')->name('get-zones');
            Route::get('get-coordinates/{id}', 'getCoordinates')->name('getCoordinates');
            Route::get('export', 'export')->name('export');
            Route::get('log', 'log')->name('log');
            Route::group(['prefix' => 'extra-fare', 'as' => 'extra-fare.'], function () {
                Route::post('store-all-zone', 'storeAllZoneExtraFare')->name('store-all-zone');
                Route::post('store', 'storeExtraFare')->name('store');
                Route::get('edit/{id}', 'editExtraFare')->name('edit');
                Route::get('status', 'statusExtraFare')->name('status');
            });
        });

    });
});

// Safety map: danger reports, pauses and no-go areas.
Route::group(['prefix' => 'admin/safety', 'as' => 'admin.safety.', 'middleware' => 'admin'], function () {
    Route::get('/', [SafetyAdminController::class, 'index'])->name('index')->middleware('can:zone_view');
    Route::middleware('can:zone_edit')->group(function () {
        Route::post('pause', [SafetyAdminController::class, 'pause'])->name('pause');
        Route::post('areas/{area}/confirm', [SafetyAdminController::class, 'confirm'])->name('confirm');
        Route::post('areas/{area}/lift', [SafetyAdminController::class, 'lift'])->name('lift');
        Route::post('reports/{report}', [SafetyAdminController::class, 'report'])->name('report');
        Route::post('settings', [SafetyAdminController::class, 'settings'])->name('settings');
        Route::post('checkin-settings', [SafetyAdminController::class, 'checkinSettings'])->name('checkin-settings');
    });
});
