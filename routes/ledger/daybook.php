<?php

// The Daybook (cash analysis book) and its analysis: paxhitestbed's daybook routes, under /accounting.
// Read-only screens: nothing here posts, so no review middleware.

use Illuminate\Support\Facades\Route;

Route::get('daybook', 'App\\Http\\Controllers\\Ledger\\DaybookController@index')->name('daybook.index');
Route::get('daybook/month/{month}', 'App\\Http\\Controllers\\Ledger\\DaybookController@month')->name('daybook.month');
Route::get('daybook/summary', 'App\\Http\\Controllers\\Ledger\\DaybookController@summary')->name('daybook.summary');
Route::get('daybook/analysis', 'App\\Http\\Controllers\\Ledger\\DaybookController@analysis')->name('daybook.analysis');
// POST because the charts travel with it, as PNGs the page captured itself.
Route::post('daybook/analysis/pdf', 'App\\Http\\Controllers\\Ledger\\DaybookController@analysisPdf')->name('daybook.analysis.pdf');
Route::get('daybook/pdf', 'App\\Http\\Controllers\\Ledger\\DaybookController@pdf')->name('daybook.pdf');
Route::get('daybook/excel', 'App\\Http\\Controllers\\Ledger\\DaybookController@excel')->name('daybook.excel');
