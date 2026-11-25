{{--
    The frame of the accounting screens (resources/views/ledger: paxhitestbed's accounting module
    as ported to posinnova, kept unchanged). posinnova's screens extend 'layouts.app', its
    AdminLTE frame; E-Ride has no other 'layouts.app', so this is E-Ride's frame for them:
    the portal header (whose books are open, back to the portal), paxhitestbed's accounting
    menu in its order (Budget, Accounting, Accounting reports), and ledger-compat.css/js,
    which let the Bootstrap 3 markup of the screens run on E-Ride's Bootstrap 5.
--}}
@php
    use App\Ledger\Permissions;
    $books = $ledgerBooks ?? \App\Ledger\ERide\Books::current();
    // [route, label, permissions (any), path prefix after partner/accounting/ that marks it active]
    $groups = [
        [__('ledger.menu_payroll_group'), [
            ['ledger.staff.index', 'ledger.menu_staff', ['staff-view'], 'staff/register'],
            ['ledger.payroll.index', 'ledger.menu_payroll', ['payroll-view', 'payroll-action', 'payroll-print'], 'staff/payroll/'],
            ['ledger.payroll.report', 'ledger.menu_payroll_report', ['payroll-report'], null],
            ['ledger.staff-loan.index', 'ledger.menu_staff_loans', ['staff-loan-view'], 'staff/staff-loan'],
            ['ledger.designation.index', 'ledger.menu_designations', ['designation-view'], 'staff/designation'],
            ['ledger.department.index', 'ledger.menu_departments', ['department-view'], 'staff/department'],
            ['ledger.allowance-type.index', 'ledger.menu_allowance_types', ['allowance-type-view'], 'staff/allowance-type'],
            ['ledger.deduction-type.index', 'ledger.menu_deduction_types', ['deduction-type-view'], 'staff/deduction-type'],
            ['ledger.tax-group.index', 'ledger.menu_tax_groups', ['tax-setting-view'], 'staff/tax-group'],
            ['ledger.tax-setting.index', 'ledger.menu_tax_settings', ['tax-setting-view'], 'staff/tax-setting'],
            ['ledger.staff-tax-report.index', 'ledger.menu_staff_tax_report', ['tax-setting-view', 'payroll-report'], 'staff/tax-report'],
            ['ledger.tax-remittance.index', 'ledger.menu_tax_remittance', ['tax-remittance-view'], 'staff/tax-remittance'],
            ['ledger.pay-slip-setting.index', 'ledger.menu_pay_slip_settings', ['pay-slip-setting-view'], 'staff/pay-slip-setting'],
            ['ledger.payroll-setting.index', 'ledger.menu_payroll_settings', ['payroll-setting'], 'staff/payroll-setting'],
        ]],
        [__('ledger.menu_income_expense'), [
            ['ledger.income.index', 'ledger.menu_income', ['income-view'], 'account/income'],
            ['ledger.income-category.index', 'ledger.menu_income_categories', ['income-category-view', 'income-view'], 'account/income-category'],
            ['ledger.expense.index', 'ledger.menu_expense', ['expense-view'], 'account/expense'],
            ['ledger.expense-category.index', 'ledger.menu_expense_categories', ['expense-category-view', 'expense-view'], 'account/expense-category'],
            ['ledger.supplier-bill.index', 'ledger.menu_supplier_bills', ['supplier-bill-view'], 'account/supplier-bills'],
            ['ledger.supplier-payment.index', 'ledger.menu_supplier_payments', ['supplier-payment-view'], 'account/supplier-payments'],
            ['ledger.supplier-bill.aging', 'ledger.menu_payables_aging_bills', ['supplier-bill-view'], null],
        ]],
        [__('ledger.menu_budget'), [
            ['ledger.budget.dashboard', 'ledger.menu_budget_dashboard', ['budget-view'], 'budget/dashboard'],
            ['ledger.budget-sheet.index', 'ledger.menu_ie_sheet', ['budget-view'], 'budget-sheet'],
            ['ledger.daybook.index', 'ledger.menu_daybook', ['daybook-view'], 'daybook'],
            ['ledger.daybook.summary', 'ledger.menu_daybook_summary', ['daybook-view'], null],
            ['ledger.daybook.analysis', 'ledger.menu_daybook_analysis', ['daybook-view'], null],
            ['ledger.budget.index', 'ledger.menu_departmental_budgets', ['budget-view'], null],
            ['ledger.budget-line.index', 'ledger.menu_budget_lines', ['budget-line-view'], 'budget-line'],
            ['ledger.budget.reports.performance', 'ledger.menu_performance_report', ['budget-view'], null],
            ['ledger.budget.reports.variance', 'ledger.menu_variance_report', ['budget-view'], null],
            ['ledger.budget.reports.department', 'ledger.menu_department_report', ['budget-view'], null],
            ['ledger.budget.reports.cashflow', 'ledger.menu_cashflow_projection', ['budget-view'], null],
        ]],
        [__('ledger.menu_payment_accounts'), [
            ['ledger.payment-account.index', 'ledger.menu_account_list', ['payment-account-view'], 'payment-account'],
            ['ledger.payment-account-transfer.index', 'ledger.menu_fund_transfers', ['payment-account-view', 'payment-account-transfer-view'], 'payment-account-transfer'],
            ['ledger.tills.index', 'ledger.menu_counter_tills', ['till-count-view'], 'tills'],
            ['ledger.payment-account-report.cashflow', 'ledger.menu_cashflow_report', ['payment-account-report-cashflow'], null],
            ['ledger.payment-account-report.unlinked', 'ledger.menu_payment_account_report', ['payment-account-report-statement'], null],
        ]],
        [__('ledger.menu_accounting'), [
            ['ledger.chart-of-accounts.index', 'ledger.menu_chart_of_accounts', ['chart-of-accounts-view'], 'chart-of-accounts'],
            ['ledger.fiscal-years.index', 'ledger.menu_fiscal_years', ['accounting-period-view'], 'fiscal-years'],
            ['ledger.journal-entries.index', 'ledger.menu_journal_entries', ['journal-entry-view'], 'journal-entries'],
            ['ledger.ledger-health', 'ledger.menu_ledger_health', ['transaction-mapping-view', 'general-ledger-view'], 'ledger-health'],
            ['ledger.general-ledger.index', 'ledger.menu_general_ledger', ['general-ledger-view'], null],
            ['ledger.general-ledger.trial-balance', 'ledger.menu_trial_balance', ['trial-balance-view'], 'general-ledger/trial-balance'],
            ['ledger.mappings.settings', 'ledger.menu_mapping_settings', ['transaction-mapping-settings'], 'mappings/settings'],
            ['ledger.cash-accounts', 'ledger.menu_cash_accounts', ['transaction-mapping-settings'], 'cash-accounts'],
            ['ledger.mappings.transactions', 'ledger.menu_view_transactions', ['transaction-mapping-view'], 'mappings/transactions'],
            ['ledger.fixed-assets.index', 'ledger.menu_asset_register', ['fixed-asset-list'], null],
            ['ledger.fixed-asset-categories.index', 'ledger.menu_asset_categories', ['fixed-asset-list'], 'fixed-asset-categories'],
            ['ledger.fixed-assets.register', 'ledger.menu_asset_report', ['fixed-asset-list'], null],
            ['ledger.fixed-assets.depreciation-report', 'ledger.menu_depreciation_report', ['fixed-asset-list'], null],
            ['ledger.bank-reconciliation.index', 'ledger.menu_bank_reconciliation', ['bank-reconciliation-list'], 'bank-reconciliation'],
            ['ledger.recurring-entries.index', 'ledger.menu_recurring_entries', ['recurring-entry-list'], 'recurring-entries'],
            ['ledger.year-end-closing.index', 'ledger.menu_year_end_closing', ['year-end-closing-list'], 'year-end-closing'],
        ]],
        [__('ledger.menu_accounting_reports'), [
            ['ledger.accounting-reports.index', 'ledger.menu_reports_dashboard', ['accounting-report-view'], null],
            ['ledger.accounting-reports.receivables-aging', 'ledger.menu_receivables_aging', ['accounting-report-view'], 'accounting-reports/receivables-aging'],
            ['ledger.accounting-reports.payables-aging', 'ledger.menu_payables_aging', ['accounting-report-view'], 'accounting-reports/payables-aging'],
            ['ledger.accounting-reports.cash-flow-statement', 'ledger.menu_cash_flow_statement', ['accounting-report-view', 'cash-flow-view'], 'accounting-reports/cash-flow'],
            ['ledger.accounting-reports.budget-vs-actual', 'ledger.menu_budget_vs_actual', ['accounting-report-view', 'budget-view'], 'accounting-reports/budget-vs-actual'],
            ['ledger.accounting-reports.financial-health', 'ledger.menu_financial_health', ['accounting-report-view'], 'accounting-reports/financial-health'],
        ]],
    ];
    // Screens not yet adapted to the bus books stay out of the menu until they are (App\Ledger\ERide\Screens).
    $ready = \App\Ledger\ERide\Screens::ready();
    $path = request()->path();
    $menu = [];
    foreach ($groups as [$title, $items]) {
        $allowed = [];
        foreach ($items as [$route, $label, $perms, $prefix]) {
            if (!Route::has($route) || !in_array($route, $ready, true) || !Permissions::can(...$perms)) {
                continue;
            }
            $active = request()->routeIs($route) || ($prefix && str_starts_with($path, 'partner/accounting/' . $prefix));
            $allowed[] = [route($route), __($label), $active];
        }
        if ($allowed) {
            $menu[] = [$title, $allowed];
        }
    }
    $portalHome = $books ? ($books['portal'] === 'bus-company' ? route('bus-company.overview') : route('bus-partner.departures')) : url('/');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#111114">
    <title>@yield('title') - {{ $books['name'] ?? 'E-Ride' }}</title>
    <link rel="icon" href="{{ dynamicAsset('public/assets/admin-module/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/ledger/fontawesome/css/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/plugins/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/ledger/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/ledger/ledger-compat.css') }}?v=2">
    @yield('css')
</head>
<body class="ledger-body">
<header class="ledger-top">
    <button type="button" class="ledger-menu-btn" aria-controls="ledger-nav" aria-expanded="false" onclick="document.body.classList.toggle('ledger-nav-open'); this.setAttribute('aria-expanded', document.body.classList.contains('ledger-nav-open'))">
        <i class="fa fa-bars" aria-hidden="true"></i><span class="visually-hidden">{{ __('ledger.menu_accounting') }}</span>
    </button>
    <div class="ledger-who">
        <strong>{{ __('ledger.menu_accounting') }}</strong>
        <span>{{ $books['name'] ?? '' }}</span>
    </div>
    <a class="ledger-back" href="{{ $portalHome }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> {{ translate('Back to the portal') }}</a>
</header>
<div class="ledger-shell">
    <nav id="ledger-nav" class="ledger-nav" aria-label="{{ __('ledger.menu_accounting') }}">
        <a href="{{ route('ledger.home') }}" class="ledger-nav-home {{ request()->routeIs('ledger.home') ? 'active' : '' }}"><i class="fa fa-book" aria-hidden="true"></i> {{ $books['name'] ?? '' }}</a>
        @foreach($menu as [$title, $items])
            <div class="ledger-nav-group">
                <div class="ledger-nav-title">{{ $title }}</div>
                @foreach($items as [$href, $label, $active])
                    <a href="{{ $href }}" class="{{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
        @endforeach
    </nav>
    <main class="ledger-main">
        @if (session('status'))
            <input type="hidden" id="status_span" data-status="{{ session('status.success') }}" data-msg="{{ session('status.msg') }}">
        @endif
        @yield('content')
    </main>
</div>
<script src="{{ dynamicAsset('public/assets/admin-module/js/jquery-3.6.0.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/plugins/select2/select2.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/ledger/toastr.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/ledger/ledger-compat.js') }}?v=1"></script>
@yield('javascript')
</body>
</html>
