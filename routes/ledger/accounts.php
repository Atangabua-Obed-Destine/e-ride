<?php

/*
 * paxhitestbed's Income & Expense and Payment Accounts, with its paths and route names
 * (admin.* there, ledger.* here). Included by routes/ledger.php inside the 'accounting'
 * prefix and the 'ledger.' names. Every route that posts carries ledger.review: its form
 * goes through the review dialog, and a submission without reviewed=1 is refused.
 */

use Illuminate\Support\Facades\Route;

$NS = 'App\\Http\\Controllers\\Ledger\\';
$R = 'ledger.review';

// ---- Income & Expense (paxhitestbed routes/web.php "Income Expense Routes")
// report before the resources, so account/income/report is never read as an id
Route::get('account/income/report', $NS.'IncomeController@report')->name('income.report');
Route::get('account/expense/report', $NS.'ExpenseController@report')->name('expense.report');
foreach (['income' => 'IncomeController', 'expense' => 'ExpenseController'] as $side => $c) {
    Route::get("account/$side", $NS.$c.'@index')->name("$side.index");
    Route::get("account/$side/create", $NS.$c.'@create')->name("$side.create");
    Route::post("account/$side", $NS.$c.'@store')->name("$side.store")->middleware($R);
    Route::get("account/$side/{id}", $NS.$c.'@show')->name("$side.show")->whereNumber('id');
    Route::get("account/$side/{id}/edit", $NS.$c.'@edit')->name("$side.edit")->whereNumber('id');
    Route::put("account/$side/{id}", $NS.$c.'@update')->name("$side.update")->middleware($R)->whereNumber('id');
    Route::delete("account/$side/{id}", $NS.$c.'@destroy')->name("$side.destroy")->middleware($R)->whereNumber('id');
}
foreach (['income-category' => 'IncomeCategoryController', 'expense-category' => 'ExpenseCategoryController'] as $path => $c) {
    Route::get("account/$path", $NS.$c.'@index')->name("$path.index");
    Route::post("account/$path", $NS.$c.'@store')->name("$path.store");
    Route::put("account/$path/{id}", $NS.$c.'@update')->name("$path.update")->whereNumber('id');
    Route::delete("account/$path/{id}", $NS.$c.'@destroy')->name("$path.destroy")->whereNumber('id');
}

// ---- Supplier bills and payments (accounts payable)
Route::get('account/supplier-bills/aging', $NS.'SupplierBillController@aging')->name('supplier-bill.aging');
Route::get('account/supplier-bills/statement', $NS.'SupplierBillController@statement')->name('supplier-bill.statement');
Route::post('account/supplier-bills/settings', $NS.'SupplierBillController@settings')->name('supplier-bill.settings');
Route::post('account/suppliers', $NS.'SupplierBillController@storeSupplier')->name('item-supplier.store');
Route::get('account/supplier-bills/{id}/preview/approve', $NS.'SupplierBillController@previewApprove')->name('supplier-bill.preview-approve')->whereNumber('id');
Route::get('account/supplier-bills/{id}/preview/void', $NS.'SupplierBillController@previewVoid')->name('supplier-bill.preview-void')->whereNumber('id');
Route::post('account/supplier-bills/{id}/approve', $NS.'SupplierBillController@approve')->name('supplier-bill.approve')->middleware($R)->whereNumber('id');
Route::post('account/supplier-bills/{id}/void', $NS.'SupplierBillController@void')->name('supplier-bill.void')->middleware($R)->whereNumber('id');
Route::get('account/supplier-bills', $NS.'SupplierBillController@index')->name('supplier-bill.index');
Route::get('account/supplier-bills/create', $NS.'SupplierBillController@create')->name('supplier-bill.create');
Route::post('account/supplier-bills', $NS.'SupplierBillController@store')->name('supplier-bill.store');
Route::get('account/supplier-bills/{id}', $NS.'SupplierBillController@show')->name('supplier-bill.show')->whereNumber('id');
Route::get('account/supplier-bills/{id}/edit', $NS.'SupplierBillController@edit')->name('supplier-bill.edit')->whereNumber('id');
Route::put('account/supplier-bills/{id}', $NS.'SupplierBillController@update')->name('supplier-bill.update')->whereNumber('id');
Route::delete('account/supplier-bills/{id}', $NS.'SupplierBillController@destroy')->name('supplier-bill.destroy')->whereNumber('id');
Route::get('account/supplier-payments/{id}/preview/void', $NS.'SupplierPaymentController@previewVoid')->name('supplier-payment.preview-void')->whereNumber('id');
Route::get('account/supplier-payments', $NS.'SupplierPaymentController@index')->name('supplier-payment.index');
Route::get('account/supplier-payments/create', $NS.'SupplierPaymentController@create')->name('supplier-payment.create');
Route::post('account/supplier-payments', $NS.'SupplierPaymentController@store')->name('supplier-payment.store')->middleware($R);
Route::get('account/supplier-payments/{id}', $NS.'SupplierPaymentController@show')->name('supplier-payment.show')->whereNumber('id');
Route::post('account/supplier-payments/{id}/void', $NS.'SupplierPaymentController@void')->name('supplier-payment.void')->middleware($R)->whereNumber('id');

// ---- Payment Accounts
Route::get('payment-account', $NS.'PaymentAccountController@index')->name('payment-account.index');
Route::get('payment-account/create', $NS.'PaymentAccountController@create')->name('payment-account.create');
Route::post('payment-account/store', $NS.'PaymentAccountController@store')->name('payment-account.store')->middleware($R);
Route::get('payment-account/{id}/edit', $NS.'PaymentAccountController@edit')->name('payment-account.edit')->whereNumber('id');
Route::put('payment-account/{id}/update', $NS.'PaymentAccountController@update')->name('payment-account.update')->whereNumber('id');
Route::delete('payment-account/{id}/delete', $NS.'PaymentAccountController@destroy')->name('payment-account.destroy')->whereNumber('id');
Route::get('payment-account/{id}/account-book', $NS.'PaymentAccountController@accountBook')->name('payment-account.account-book')->whereNumber('id');
Route::get('payment-account/{id}/deposit', $NS.'PaymentAccountController@deposit')->name('payment-account.deposit')->whereNumber('id');
Route::post('payment-account/{id}/deposit', $NS.'PaymentAccountController@deposit')->name('payment-account.deposit.post')->middleware($R)->whereNumber('id');
Route::get('payment-account/{id}/withdraw', $NS.'PaymentAccountController@withdraw')->name('payment-account.withdraw')->whereNumber('id');
Route::post('payment-account/{id}/withdraw', $NS.'PaymentAccountController@withdraw')->name('payment-account.withdraw.post')->middleware($R)->whereNumber('id');
Route::get('payment-account/{id}/transactions-data', $NS.'PaymentAccountController@getTransactions')->name('payment-account.transactions-data')->whereNumber('id');
Route::get('payment-account/transaction/{id}/edit', $NS.'PaymentAccountController@editTransaction')->name('payment-account.transaction.edit')->whereNumber('id');
Route::put('payment-account/transaction/{id}/update', $NS.'PaymentAccountController@updateTransaction')->name('payment-account.transaction.update')->middleware($R)->whereNumber('id');
Route::delete('payment-account/transaction/{id}/delete', $NS.'PaymentAccountController@deleteTransaction')->name('payment-account.transaction.delete')->middleware($R)->whereNumber('id');

Route::get('payment-account-transfer', $NS.'PaymentAccountTransferController@index')->name('payment-account-transfer.index');
Route::get('payment-account-transfer/create', $NS.'PaymentAccountTransferController@create')->name('payment-account-transfer.create');
Route::post('payment-account-transfer/store', $NS.'PaymentAccountTransferController@store')->name('payment-account-transfer.store')->middleware($R);
Route::get('payment-account-transfer/{id}/edit', $NS.'PaymentAccountTransferController@edit')->name('payment-account-transfer.edit')->whereNumber('id');
Route::put('payment-account-transfer/{id}/update', $NS.'PaymentAccountTransferController@update')->name('payment-account-transfer.update')->middleware($R)->whereNumber('id');
Route::delete('payment-account-transfer/{id}/delete', $NS.'PaymentAccountTransferController@destroy')->name('payment-account-transfer.destroy')->middleware($R)->whereNumber('id');

Route::get('payment-account-report/cashflow', $NS.'PaymentAccountReportController@cashflow')->name('payment-account-report.cashflow');
Route::get('payment-account-report/statement/{id}', $NS.'PaymentAccountReportController@accountStatement')->name('payment-account-report.statement')->whereNumber('id');
Route::get('payment-account-report/summary', $NS.'PaymentAccountReportController@summary')->name('payment-account-report.summary');
Route::get('payment-account-report/unlinked', $NS.'PaymentAccountReportController@unlinkedTransactions')->name('payment-account-report.unlinked');
Route::post('payment-account-report/link', $NS.'PaymentAccountReportController@linkTransaction')->name('payment-account-report.link')->middleware($R);
