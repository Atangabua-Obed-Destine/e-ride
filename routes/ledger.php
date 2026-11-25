<?php

/*
 * PosInnova accounting: the paxhitestbed ledger. Same paths and route names as
 * paxhitestbed (admin.* there, ledger.* here), under /accounting. Included from
 * routes/web.php inside the signed-in group; every controller also checks that
 * Accounting is switched on for the business (App\Http\Controllers\Ledger\LedgerController).
 */

use Illuminate\Support\Facades\Route;

Route::prefix('accounting')->name('ledger.')->group(function () {
    $NS = 'App\\Http\\Controllers\\Ledger\\';

    // First run: set up the books (SYSCOHADA chart, this year and its months, default mappings)
    Route::get('setup', $NS.'SetupController@index')->name('setup');
    Route::post('setup', $NS.'SetupController@store')->name('setup.store');
    Route::get('/', $NS.'SetupController@home')->name('home');

    // The review of any form marked data-review: its real request replayed and rolled back
    Route::post('review/dry-run', $NS.'ReviewController@dryRun')->name('review.dry-run');

    // Chart of Accounts
    Route::resource('chart-of-accounts', $NS.'ChartOfAccountController');
    Route::post('chart-of-accounts/{id}/toggle-status', $NS.'ChartOfAccountController@toggleStatus')->name('chart-of-accounts.toggle-status');
    Route::get('chart-of-accounts-by-class', $NS.'ChartOfAccountController@getByClass')->name('chart-of-accounts.by-class');

    // Fiscal Years, and closing / reopening one month through the review dialog
    Route::resource('fiscal-years', $NS.'FiscalYearController');
    Route::post('fiscal-years/{id}/close', $NS.'FiscalYearController@close')->name('fiscal-years.close')->middleware('ledger.review');
    Route::post('fiscal-years/{id}/reopen', $NS.'FiscalYearController@reopen')->name('fiscal-years.reopen')->middleware('ledger.review');
    Route::post('fiscal-years/{id}/set-active', $NS.'FiscalYearController@setActive')->name('fiscal-years.set-active');
    Route::post('fiscal-years/{id}/generate-periods', $NS.'FiscalYearController@generatePeriods')->name('fiscal-years.generate-periods');
    Route::get('accounting-periods/{id}/preview/close', $NS.'FiscalYearController@previewClosePeriod')->name('accounting-periods.preview-close');
    Route::post('accounting-periods/{id}/close', $NS.'FiscalYearController@closePeriod')->name('accounting-periods.close');
    Route::get('accounting-periods/{id}/preview/reopen', $NS.'FiscalYearController@previewReopenPeriod')->name('accounting-periods.preview-reopen');
    Route::post('accounting-periods/{id}/reopen', $NS.'FiscalYearController@reopenPeriod')->name('accounting-periods.reopen');

    // Ledger health (before the journal-entries resource so it is never read as an id)
    Route::get('ledger-health', $NS.'LedgerHealthController@index')->name('ledger-health');

    // Journal Entries
    Route::resource('journal-entries', $NS.'JournalEntryController');
    Route::get('journal-entries-trash', $NS.'JournalEntryController@trash')->name('journal-entries.trash');
    Route::post('journal-entries/{id}/restore', $NS.'JournalEntryController@restore')->name('journal-entries.restore');
    Route::delete('journal-entries/{id}/force-delete', $NS.'JournalEntryController@forceDelete')->name('journal-entries.force-delete');
    Route::match(['get', 'post'], 'journal-entries/{id}/preview/post', $NS.'JournalEntryController@previewPost')->name('journal-entries.preview-post');
    Route::post('journal-entries/{id}/post', $NS.'JournalEntryController@post')->name('journal-entries.post');
    Route::post('journal-entries/{id}/unpost', $NS.'JournalEntryController@unpost')->name('journal-entries.unpost')->middleware('ledger.review');
    Route::post('journal-entries/{id}/duplicate', $NS.'JournalEntryController@duplicate')->name('journal-entries.duplicate');
    Route::match(['get', 'post'], 'journal-entries/{id}/preview/reverse', $NS.'JournalEntryController@previewReverse')->name('journal-entries.preview-reverse');
    Route::post('journal-entries/{id}/reverse', $NS.'JournalEntryController@reverse')->name('journal-entries.reverse');

    // General Ledger and the statements
    Route::get('general-ledger', $NS.'GeneralLedgerController@index')->name('general-ledger.index');
    Route::get('general-ledger/account/{id}', $NS.'GeneralLedgerController@account')->name('general-ledger.account');
    Route::get('general-ledger/class/{classNumber}', $NS.'GeneralLedgerController@byClass')->name('general-ledger.by-class');
    Route::get('general-ledger/trial-balance', $NS.'GeneralLedgerController@trialBalance')->name('general-ledger.trial-balance');
    Route::get('general-ledger/balance-sheet', $NS.'GeneralLedgerController@balanceSheet')->name('general-ledger.balance-sheet');
    Route::get('general-ledger/income-statement', $NS.'GeneralLedgerController@incomeStatement')->name('general-ledger.income-statement');
    Route::get('general-ledger/account/{id}/export-pdf', $NS.'GeneralLedgerController@exportAccountPDF')->name('general-ledger.export-pdf');
    Route::get('general-ledger/trial-balance/export-excel', $NS.'GeneralLedgerController@exportTrialBalanceExcel')->name('general-ledger.export-excel');
    Route::get('general-ledger/balance-sheet/export-pdf', $NS.'GeneralLedgerController@exportBalanceSheetPDF')->name('general-ledger.balance-sheet-pdf');
    Route::get('general-ledger/balance-sheet/export-excel', $NS.'GeneralLedgerController@exportBalanceSheetExcel')->name('general-ledger.balance-sheet-excel');
    Route::get('general-ledger/income-statement/export-pdf', $NS.'GeneralLedgerController@exportIncomeStatementPDF')->name('general-ledger.income-statement-pdf');
    Route::get('general-ledger/income-statement/export-excel', $NS.'GeneralLedgerController@exportIncomeStatementExcel')->name('general-ledger.income-statement-excel');

    // Transaction mappings: which account each kind of document uses, cash and bank, the documents and their entries
    Route::get('mappings/settings', $NS.'AccountMappingController@settings')->name('mappings.settings');
    Route::post('mappings/save-default', $NS.'AccountMappingController@saveDefaultMappings')->name('mappings.save-default');
    Route::get('mappings/transactions', $NS.'AccountMappingController@transactionsList')->name('mappings.transactions');
    Route::post('mappings/bulk-sync', $NS.'AccountMappingController@bulkSync')->name('mappings.bulk-sync');
    Route::post('mappings/{mappingId}/remap', $NS.'AccountMappingController@remap')->name('mappings.remap');
    Route::get('cash-accounts', $NS.'CashAccountController@index')->name('cash-accounts');
    Route::post('cash-accounts', $NS.'CashAccountController@update')->name('cash-accounts.update');

    // Stage 2 areas: each in its own file (routes/ledger/*.php), inside this group
    foreach (glob(__DIR__.'/ledger/*.php') ?: [] as $area) {
        require $area;
    }
});
