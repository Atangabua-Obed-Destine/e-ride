<?php

/*
 * paxhitestbed's Human Resource payroll and tax, with its paths (staff/...) and route names
 * (admin.* there, ledger.* here). Included by routes/ledger.php inside the 'accounting' prefix and
 * the 'ledger.' names. Every route that posts carries ledger.review: its form goes through the
 * review dialog, and a submission without reviewed=1 is refused. Generating, approving and the
 * settings post nothing (a payroll posts when it is paid).
 *
 * Menu order (paxhitestbed's sidebar): Staff list, Payroll, Payroll report, Loans and Advances,
 * Designations, Departments, Allowance types, Deduction types, then Tax groups, Tax settings, Staff
 * tax report, Tax remittance, Pay slip settings, Payroll settings.
 */

use Illuminate\Support\Facades\Route;

$NS = 'App\\Http\\Controllers\\Ledger\\Payroll\\';
$R = 'ledger.review';

// ---- the staff register (paxhitestbed's staff list: its users with HR fields)
Route::get('staff/register', $NS.'StaffController@index')->name('staff.index');
Route::get('staff/register/create', $NS.'StaffController@create')->name('staff.create');
Route::post('staff/register', $NS.'StaffController@store')->name('staff.store');
Route::get('staff/register/{id}/edit', $NS.'StaffController@edit')->name('staff.edit')->whereNumber('id');
Route::put('staff/register/{id}', $NS.'StaffController@update')->name('staff.update')->whereNumber('id');
Route::delete('staff/register/{id}', $NS.'StaffController@destroy')->name('staff.destroy')->whereNumber('id');

// ---- payroll
Route::get('staff/payroll', $NS.'PayrollController@index')->name('payroll.index');
Route::post('staff/payroll', $NS.'PayrollController@store')->name('payroll.store');
Route::post('staff/payroll-preview', $NS.'PayrollController@preview')->name('payroll.preview');
Route::get('staff/payroll-generate/{id}/{month}/{year}', $NS.'PayrollController@generate')->name('payroll.generate')->whereNumber(['id', 'month', 'year']);
Route::post('staff/payroll-pay/{id}', $NS.'PayrollController@pay')->name('payroll.pay')->middleware($R)->whereNumber('id');
Route::post('staff/payroll-unpay/{id}', $NS.'PayrollController@unpay')->name('payroll.unpay')->middleware($R)->whereNumber('id');
Route::post('staff/payroll-approve/{id}', $NS.'PayrollController@approve')->name('payroll.approve')->whereNumber('id');
Route::post('staff/payroll-bulk', $NS.'PayrollController@bulk')->name('payroll.bulk');
Route::post('staff/payroll-bulk-pay', $NS.'PayrollController@bulkPay')->name('payroll.bulk-pay')->middleware($R);
Route::get('staff/payroll-bank-file', $NS.'PayrollController@bankFile')->name('payroll.bank-file');
Route::get('staff/payroll-report', $NS.'PayrollController@report')->name('payroll.report');
Route::get('staff/payroll-print/{id}', $NS.'PayrollController@print')->name('payroll.print')->whereNumber('id');
Route::get('staff/payroll-pdf/{id}', $NS.'PayrollController@pdf')->name('payroll.pdf')->whereNumber('id');
Route::get('staff/payroll-setting', $NS.'PayrollSettingController@index')->name('payroll-setting.index');
Route::post('staff/payroll-setting', $NS.'PayrollSettingController@update')->name('payroll-setting.update');

// ---- loans and advances
Route::get('staff/staff-loan', $NS.'StaffLoanController@index')->name('staff-loan.index');
Route::post('staff/staff-loan', $NS.'StaffLoanController@store')->name('staff-loan.store')->middleware($R);
Route::post('staff/staff-loan/{id}/cancel', $NS.'StaffLoanController@cancel')->name('staff-loan.cancel')->middleware($R)->whereNumber('id');

// ---- the lists: designations, departments, allowance and deduction types
foreach (['designation' => 'DesignationController', 'department' => 'DepartmentController',
    'allowance-type' => 'AllowanceTypeController', 'deduction-type' => 'DeductionTypeController'] as $path => $c) {
    Route::get("staff/$path", $NS.$c.'@index')->name("$path.index");
    Route::post("staff/$path", $NS.$c.'@store')->name("$path.store");
    Route::put("staff/$path/{id}", $NS.$c.'@update')->name("$path.update")->whereNumber('id');
    Route::delete("staff/$path/{id}", $NS.$c.'@destroy')->name("$path.destroy")->whereNumber('id');
}

// ---- tax
Route::get('staff/tax-group', $NS.'TaxGroupController@index')->name('tax-group.index');
Route::get('staff/tax-group/create', $NS.'TaxGroupController@create')->name('tax-group.create');
Route::post('staff/tax-group', $NS.'TaxGroupController@store')->name('tax-group.store');
Route::get('staff/tax-group/{id}', $NS.'TaxGroupController@show')->name('tax-group.show')->whereNumber('id');
Route::get('staff/tax-group/{id}/edit', $NS.'TaxGroupController@edit')->name('tax-group.edit')->whereNumber('id');
Route::put('staff/tax-group/{id}', $NS.'TaxGroupController@update')->name('tax-group.update')->whereNumber('id');
Route::delete('staff/tax-group/{id}', $NS.'TaxGroupController@destroy')->name('tax-group.destroy')->whereNumber('id');
Route::post('staff/tax-group/{id}/bracket', $NS.'TaxGroupController@storeBracket')->name('tax-group.bracket.store')->whereNumber('id');
Route::put('staff/tax-group/{groupId}/bracket/{bracketId}', $NS.'TaxGroupController@updateBracket')->name('tax-group.bracket.update')->whereNumber(['groupId', 'bracketId']);
Route::delete('staff/tax-group/{groupId}/bracket/{bracketId}', $NS.'TaxGroupController@destroyBracket')->name('tax-group.bracket.destroy')->whereNumber(['groupId', 'bracketId']);

Route::get('staff/tax-setting', $NS.'TaxSettingController@index')->name('tax-setting.index');
Route::post('staff/tax-setting', $NS.'TaxSettingController@store')->name('tax-setting.store');
Route::put('staff/tax-setting/{id}', $NS.'TaxSettingController@update')->name('tax-setting.update')->whereNumber('id');
Route::delete('staff/tax-setting/{id}', $NS.'TaxSettingController@destroy')->name('tax-setting.destroy')->whereNumber('id');
Route::post('staff/tax-exemptions/store', $NS.'TaxSettingController@storeExemption')->name('tax-exemptions.store');
Route::delete('staff/tax-exemptions/destroy/{tax_setting_id}/{staff_id}', $NS.'TaxSettingController@destroyExemption')->name('tax-exemptions.destroy')->whereNumber(['tax_setting_id', 'staff_id']);

Route::get('staff/tax-remittance', $NS.'TaxRemittanceController@index')->name('tax-remittance.index');
Route::post('staff/tax-remittance', $NS.'TaxRemittanceController@store')->name('tax-remittance.store')->middleware($R);
Route::post('staff/tax-remittance/{id}/void', $NS.'TaxRemittanceController@void')->name('tax-remittance.void')->middleware($R)->whereNumber('id');

Route::get('staff/tax-report', $NS.'StaffTaxReportController@index')->name('staff-tax-report.index');
Route::get('staff/tax-report/pdf', $NS.'StaffTaxReportController@exportPdf')->name('staff-tax-report.pdf');
Route::get('staff/tax-report/excel', $NS.'StaffTaxReportController@exportExcel')->name('staff-tax-report.excel');

Route::get('staff/pay-slip-setting', $NS.'PaySlipSettingController@index')->name('pay-slip-setting.index');
Route::post('staff/pay-slip-setting', $NS.'PaySlipSettingController@store')->name('pay-slip-setting.store');
