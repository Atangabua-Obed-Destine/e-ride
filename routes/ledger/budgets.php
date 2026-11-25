<?php

// Budgets (paxhitestbed's admin.budget*, admin.budget-line.*, admin.budget-sheet.*, budget reports and
// Budget vs Actual), same paths and names under /accounting and ledger.*. Approving and revising go
// through the review dialog (ledger.review). Lines before budget/{id} so no word is read as an id.

// Budget lines (the rows of the Income & Expenditure sheet)
Route::get('budget-line', 'App\\Http\\Controllers\\Ledger\\BudgetLineController@index')->name('budget-line.index');
Route::post('budget-line/store', 'App\\Http\\Controllers\\Ledger\\BudgetLineController@store')->name('budget-line.store');
Route::post('budget-line/standard', 'App\\Http\\Controllers\\Ledger\\BudgetLineController@standard')->name('budget-line.standard');
Route::post('budget-line/reorder', 'App\\Http\\Controllers\\Ledger\\BudgetLineController@reorder')->name('budget-line.reorder');
Route::post('budget-line/auto-sort', 'App\\Http\\Controllers\\Ledger\\BudgetLineController@autoSort')->name('budget-line.auto-sort');
Route::post('budget-line/{id}/update', 'App\\Http\\Controllers\\Ledger\\BudgetLineController@update')->name('budget-line.update');
Route::post('budget-line/{id}/toggle', 'App\\Http\\Controllers\\Ledger\\BudgetLineController@toggle')->name('budget-line.toggle');
Route::post('budget-line/{id}/delete', 'App\\Http\\Controllers\\Ledger\\BudgetLineController@destroy')->name('budget-line.delete');

// The Income & Expenditure sheet
Route::get('budget-sheet', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@index')->name('budget-sheet.index');
Route::post('budget-sheet/store', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@store')->name('budget-sheet.store');
// The sheet covering a month (YYYY-MM), for links from month screens such as the Daybook; before {id}
Route::get('budget-sheet/month/{month}', 'App\Http\Controllers\Ledger\BudgetSheetController@month')->name('budget-sheet.month');
Route::get('budget-sheet/{id}/pdf', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@exportPdf')->name('budget-sheet.pdf');
Route::get('budget-sheet/{id}/excel', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@exportExcel')->name('budget-sheet.excel');
Route::get('budget-sheet/{id}', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@show')->name('budget-sheet.show');
Route::post('budget-sheet/{id}/figures', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@saveFigures')->name('budget-sheet.figures');
Route::post('budget-sheet/{id}/unit-basis', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@unitBasis')->name('budget-sheet.unit-basis');
Route::post('budget-sheet/{id}/period', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@updatePeriod')->name('budget-sheet.period');
Route::post('budget-sheet/{id}/delete', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@destroy')->name('budget-sheet.delete');
Route::post('budget-sheet/{id}/submit', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@submit')->name('budget-sheet.submit');
Route::post('budget-sheet/{id}/approve', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@approve')->name('budget-sheet.approve')->middleware('ledger.review');
Route::post('budget-sheet/{id}/activate', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@activate')->name('budget-sheet.activate');
Route::post('budget-sheet/{id}/close', 'App\\Http\\Controllers\\Ledger\\BudgetSheetController@close')->name('budget-sheet.close');

// Departmental budgets
Route::get('budget', 'App\\Http\\Controllers\\Ledger\\BudgetController@index')->name('budget.index');
Route::get('budget/create', 'App\\Http\\Controllers\\Ledger\\BudgetController@create')->name('budget.create');
Route::post('budget/store', 'App\\Http\\Controllers\\Ledger\\BudgetController@store')->name('budget.store');
Route::get('budget/{id}', 'App\\Http\\Controllers\\Ledger\\BudgetController@show')->name('budget.show');
Route::get('budget/{id}/edit', 'App\\Http\\Controllers\\Ledger\\BudgetController@edit')->name('budget.edit');
Route::post('budget/{id}/update', 'App\\Http\\Controllers\\Ledger\\BudgetController@update')->name('budget.update');
Route::post('budget/{id}/delete', 'App\\Http\\Controllers\\Ledger\\BudgetController@destroy')->name('budget.delete');
Route::post('budget/{id}/submit', 'App\\Http\\Controllers\\Ledger\\BudgetController@submitForApproval')->name('budget.submit');
Route::post('budget/{id}/approve', 'App\\Http\\Controllers\\Ledger\\BudgetController@approve')->name('budget.approve')->middleware('ledger.review');
Route::post('budget/{id}/activate', 'App\\Http\\Controllers\\Ledger\\BudgetController@activate')->name('budget.activate');
Route::post('budget/{id}/close', 'App\\Http\\Controllers\\Ledger\\BudgetController@close')->name('budget.close');
Route::post('budget/{id}/cancel', 'App\\Http\\Controllers\\Ledger\\BudgetController@cancel')->name('budget.cancel');
Route::post('budget/{id}/revise', 'App\\Http\\Controllers\\Ledger\\BudgetController@revise')->name('budget.revise')->middleware('ledger.review');
Route::get('budget/{id}/allocations-data', 'App\\Http\\Controllers\\Ledger\\BudgetController@getAllocationsData')->name('budget.allocations-data');

// Allocations of a departmental budget
Route::get('budget/{budgetId}/allocations', 'App\\Http\\Controllers\\Ledger\\BudgetAllocationController@index')->name('budget.allocations');
Route::post('budget/{budgetId}/allocations/store', 'App\\Http\\Controllers\\Ledger\\BudgetAllocationController@store')->name('budget.allocations.store');
Route::post('budget/{budgetId}/allocations/{id}/update', 'App\\Http\\Controllers\\Ledger\\BudgetAllocationController@update')->name('budget.allocations.update');
Route::post('budget/{budgetId}/allocations/{id}/delete', 'App\\Http\\Controllers\\Ledger\\BudgetAllocationController@destroy')->name('budget.allocations.delete');

// Dashboard and reports
Route::get('budget-dashboard', 'App\\Http\\Controllers\\Ledger\\BudgetDashboardController@index')->name('budget.dashboard');
Route::get('budget-dashboard/{id}/summary', 'App\\Http\\Controllers\\Ledger\\BudgetDashboardController@getBudgetSummary')->name('budget.dashboard.summary');
Route::get('budget-reports/performance', 'App\\Http\\Controllers\\Ledger\\BudgetReportController@performance')->name('budget.reports.performance');
Route::get('budget-reports/variance', 'App\\Http\\Controllers\\Ledger\\BudgetReportController@variance')->name('budget.reports.variance');
Route::get('budget-reports/department', 'App\\Http\\Controllers\\Ledger\\BudgetReportController@department')->name('budget.reports.department');
Route::get('budget-reports/cashflow', 'App\\Http\\Controllers\\Ledger\\BudgetReportController@cashflow')->name('budget.reports.cashflow');
Route::get('accounting-reports/budget-vs-actual', 'App\\Http\\Controllers\\Ledger\\BudgetReportController@budgetVsActual')->name('accounting-reports.budget-vs-actual');
