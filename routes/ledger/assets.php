<?php

// Fixed assets and depreciation (paxhitestbed admin.fixed-assets.*, admin.fixed-asset-categories.*), and Financial health.
// Required inside the /accounting prefix and the ledger. name group of routes/ledger.php.

use Illuminate\Support\Facades\Route;

Route::post('fixed-assets/calculate-depreciation', 'App\\Http\\Controllers\\Ledger\\FixedAssetController@calculateDepreciation')->name('fixed-assets.calculate-depreciation');
Route::post('fixed-assets/post-depreciation', 'App\\Http\\Controllers\\Ledger\\FixedAssetController@postDepreciation')->name('fixed-assets.post-depreciation')->middleware('ledger.review');
Route::post('fixed-assets/{fixedAsset}/dispose', 'App\\Http\\Controllers\\Ledger\\FixedAssetController@dispose')->name('fixed-assets.dispose')->middleware('ledger.review');
Route::post('fixed-assets/{fixedAsset}/post-purchase', 'App\\Http\\Controllers\\Ledger\\FixedAssetController@postPurchase')->name('fixed-assets.post-purchase')->middleware('ledger.review');
Route::resource('fixed-assets', 'App\\Http\\Controllers\\Ledger\\FixedAssetController');
Route::get('fixed-assets-register', 'App\\Http\\Controllers\\Ledger\\FixedAssetController@assetRegister')->name('fixed-assets.register');
Route::get('fixed-assets-depreciation-report', 'App\\Http\\Controllers\\Ledger\\FixedAssetController@depreciationScheduleReport')->name('fixed-assets.depreciation-report');
Route::get('fixed-assets-depreciation-summary', 'App\\Http\\Controllers\\Ledger\\FixedAssetController@depreciationSummary')->name('fixed-assets.depreciation-summary');
Route::resource('fixed-asset-categories', 'App\\Http\\Controllers\\Ledger\\FixedAssetCategoryController');
Route::get('accounting-reports/financial-health', 'App\\Http\\Controllers\\Ledger\\FinancialHealthController@index')->name('accounting-reports.financial-health');
