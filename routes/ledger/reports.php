<?php

// Accounting reports (paxhitestbed's admin.accounting-reports.*): dashboard, aging, cash flow, exports. Nothing here posts.
Route::get('accounting-reports', 'App\\Http\\Controllers\\Ledger\\AccountingReportsController@index')->name('accounting-reports.index');
Route::get('accounting-reports/receivables-aging', 'App\\Http\\Controllers\\Ledger\\AccountingReportsController@receivablesAging')->name('accounting-reports.receivables-aging');
Route::get('accounting-reports/payables-aging', 'App\\Http\\Controllers\\Ledger\\AccountingReportsController@payablesAging')->name('accounting-reports.payables-aging');
Route::get('accounting-reports/cash-flow-statement', 'App\\Http\\Controllers\\Ledger\\AccountingReportsController@cashFlowStatement')->name('accounting-reports.cash-flow-statement');
Route::get('accounting-reports/comparative-cash-flow', 'App\\Http\\Controllers\\Ledger\\AccountingReportsController@comparativeCashFlow')->name('accounting-reports.comparative-cash-flow');
Route::get('accounting-reports/export-pdf', 'App\\Http\\Controllers\\Ledger\\AccountingReportsController@exportPdf')->name('accounting-reports.export-pdf');
Route::get('accounting-reports/export-excel', 'App\\Http\\Controllers\\Ledger\\AccountingReportsController@exportExcel')->name('accounting-reports.export-excel');
