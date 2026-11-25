<?php

// Bank reconciliation, recurring entries, year-end closing (paxhitestbed's paths and route names, ledger.* here).
// Required inside the /accounting prefix and the ledger. name group (routes/ledger.php).

use Illuminate\Support\Facades\Route;

// Bank reconciliation (rapprochement bancaire)
Route::get('bank-reconciliation-summary', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@summary')->name('bank-reconciliation.summary');
Route::resource('bank-reconciliation', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController')->except(['edit', 'update']);
Route::post('bank-reconciliation/{id}/complete', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@complete')->name('bank-reconciliation.complete')->middleware('ledger.review');
Route::post('bank-reconciliation/{id}/recalculate', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@recalculate')->name('bank-reconciliation.recalculate');
Route::post('bank-reconciliation/{id}/update-balance', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@updateStatementBalance')->name('bank-reconciliation.update-balance');
Route::post('bank-reconciliation-item/{item}/clear', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@clearItem')->name('bank-reconciliation.clear-item');
Route::post('bank-reconciliation-item/{item}/unclear', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@unclearItem')->name('bank-reconciliation.unclear-item');
Route::post('bank-reconciliation/{id}/add-adjustment', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@addAdjustment')->name('bank-reconciliation.add-adjustment')->middleware('ledger.review');
Route::post('bank-reconciliation/{id}/preview/adjustment', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@previewAdjustment')->name('bank-reconciliation.preview-adjustment');
Route::get('bank-reconciliation/{id}/preview/complete', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@previewComplete')->name('bank-reconciliation.preview-complete');
Route::post('bank-reconciliation/{id}/ticks', 'App\\Http\\Controllers\\Ledger\\BankReconciliationController@saveTicks')->name('bank-reconciliation.ticks');

// Recurring entries (écritures récurrentes)
Route::post('recurring-entries-process-all', 'App\\Http\\Controllers\\Ledger\\RecurringEntryController@processAll')->name('recurring-entries.process-all')->middleware('ledger.review');
Route::get('recurring-entries-upcoming', 'App\\Http\\Controllers\\Ledger\\RecurringEntryController@upcoming')->name('recurring-entries.upcoming');
Route::resource('recurring-entries', 'App\\Http\\Controllers\\Ledger\\RecurringEntryController');
Route::post('recurring-entries/{id}/pause', 'App\\Http\\Controllers\\Ledger\\RecurringEntryController@pause')->name('recurring-entries.pause');
Route::post('recurring-entries/{id}/resume', 'App\\Http\\Controllers\\Ledger\\RecurringEntryController@resume')->name('recurring-entries.resume');
Route::post('recurring-entries/{id}/skip-next', 'App\\Http\\Controllers\\Ledger\\RecurringEntryController@skipNext')->name('recurring-entries.skip-next');
Route::post('recurring-entries/{id}/process', 'App\\Http\\Controllers\\Ledger\\RecurringEntryController@process')->name('recurring-entries.process')->middleware('ledger.review');
Route::post('recurring-entries/{id}/duplicate', 'App\\Http\\Controllers\\Ledger\\RecurringEntryController@duplicate')->name('recurring-entries.duplicate');

// Year-end closing (clôture de l'exercice)
Route::resource('year-end-closing', 'App\\Http\\Controllers\\Ledger\\YearEndClosingController')->except(['edit', 'update']);
Route::post('year-end-closing/{id}/start', 'App\\Http\\Controllers\\Ledger\\YearEndClosingController@start')->name('year-end-closing.start');
Route::post('year-end-closing/{id}/update-checklist', 'App\\Http\\Controllers\\Ledger\\YearEndClosingController@updateChecklist')->name('year-end-closing.update-checklist');
Route::post('year-end-closing/{id}/stock', 'App\\Http\\Controllers\\Ledger\\YearEndClosingController@updateStock')->name('year-end-closing.stock');
Route::post('year-end-closing/{id}/generate-entries', 'App\\Http\\Controllers\\Ledger\\YearEndClosingController@generateClosingEntries')->name('year-end-closing.generate-entries')->middleware('ledger.review');
Route::post('year-end-closing/{id}/approve', 'App\\Http\\Controllers\\Ledger\\YearEndClosingController@approve')->name('year-end-closing.approve')->middleware('ledger.review');
Route::get('year-end-closing/{id}/preview/reverse', 'App\\Http\\Controllers\\Ledger\\YearEndClosingController@previewReverse')->name('year-end-closing.preview-reverse');
Route::post('year-end-closing/{id}/reverse', 'App\\Http\\Controllers\\Ledger\\YearEndClosingController@reverse')->name('year-end-closing.reverse')->middleware('ledger.review');
