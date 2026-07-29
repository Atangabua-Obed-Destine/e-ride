<?php

use Illuminate\Support\Facades\Route;
use Modules\TransactionManagement\Http\Controllers\Web\Admin\Report\ReportController;
use Modules\TransactionManagement\Http\Controllers\Web\Admin\Transaction\TransactionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'transaction', 'as' => 'transaction.'], function () {
        Route::controller(TransactionController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('export', 'export')->name('export');
        });
    });
    Route::group(['prefix' => 'report', 'as' => 'report.'], function () {
        Route::controller(ReportController::class)->group(function () {
            Route::get('earning', 'earningReport')->name('earning');
            Route::get('earningReportExport', 'earningReportExport')->name('earningReportExport');
            Route::get('singleEarningReportExport/{id}', 'singleEarningReportExport')->name('singleEarningReportExport');
            Route::get('dateZoneWiseEarningStatistics', 'dateZoneWiseEarningStatistics')->name('dateZoneWiseEarningStatistics');
            Route::get('dateRideTypeWiseEarningStatistics', 'dateRideTypeWiseEarningStatistics')->name('dateRideTypeWiseEarningStatistics');
            Route::get('dateZoneWiseExpenseStatistics', 'dateZoneWiseExpenseStatistics')->name('dateZoneWiseExpenseStatistics');
            Route::get('dateRideTypeWiseExpenseStatistics', 'dateRideTypeWiseExpenseStatistics')->name('dateRideTypeWiseExpenseStatistics');
            Route::get('expense', 'expenseReport')->name('expense');
            Route::get('expenseReportExport', 'expenseReportExport')->name('expenseReportExport');
            Route::get('singleExpenseReportExport/{id}', 'singleExpenseReportExport')->name('singleExpenseReportExport');
        });
    });
});

