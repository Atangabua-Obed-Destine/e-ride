<?php

namespace App\Ledger\ERide;

/**
 * Which of posinnova's accounting screens are adapted to the bus books and shown
 * in the menu. The rebuild goes in stages (plan of 2026-11-25); a screen whose
 * data still comes from posinnova's sales tables stays out of the menu until its
 * bus version is ready, rather than showing something wrong.
 */
class Screens
{
    public const STAGE_1 = [
        'ledger.chart-of-accounts.index',
        'ledger.fiscal-years.index',
        'ledger.journal-entries.index',
        'ledger.general-ledger.index',
        'ledger.general-ledger.trial-balance',
        'ledger.mappings.settings',
        'ledger.cash-accounts',
        'ledger.mappings.transactions',
        'ledger.tills.index',
        // stage 2
        'ledger.bank-reconciliation.index',
        'ledger.recurring-entries.index',
        'ledger.year-end-closing.index',
        'ledger.income.index', 'ledger.income-category.index', 'ledger.expense.index', 'ledger.expense-category.index',
        'ledger.supplier-bill.index', 'ledger.supplier-payment.index', 'ledger.supplier-bill.aging',
        'ledger.payment-account.index', 'ledger.payment-account-transfer.index',
        'ledger.payment-account-report.cashflow', 'ledger.payment-account-report.unlinked',
        'ledger.ledger-health',
        'ledger.daybook.index', 'ledger.daybook.summary', 'ledger.daybook.analysis',
        'ledger.accounting-reports.index', 'ledger.accounting-reports.financial-health', 'ledger.accounting-reports.receivables-aging',
        'ledger.accounting-reports.payables-aging', 'ledger.accounting-reports.cash-flow-statement', 'ledger.accounting-reports.budget-vs-actual',
        // payroll and tax (paxhitestbed's HR payroll area)
        'ledger.staff.index', 'ledger.payroll.index', 'ledger.payroll.report', 'ledger.staff-loan.index', 'ledger.designation.index',
        'ledger.department.index', 'ledger.allowance-type.index', 'ledger.deduction-type.index', 'ledger.tax-group.index',
        'ledger.tax-setting.index', 'ledger.staff-tax-report.index', 'ledger.tax-remittance.index', 'ledger.pay-slip-setting.index',
        'ledger.payroll-setting.index',
        'ledger.staff-hourly-attendance.index', 'ledger.staff-hourly-attendance.report',
        'ledger.combined.index',
        // budgets
        'ledger.budget.dashboard', 'ledger.budget-sheet.index', 'ledger.budget.index', 'ledger.budget-line.index',
        'ledger.budget.reports.performance', 'ledger.budget.reports.variance', 'ledger.budget.reports.department', 'ledger.budget.reports.cashflow',
        // fixed assets: a bus of the fleet as an asset, its monthly wear tagged with the bus (tests/Ledger/fixed_assets_test.php)
        'ledger.fixed-assets.index', 'ledger.fixed-asset-categories.index', 'ledger.fixed-assets.register', 'ledger.fixed-assets.depreciation-report',
    ];

    public static function ready(): array
    {
        return self::STAGE_1;
    }

    public static function isReady(string $route): bool
    {
        return in_array($route, self::ready(), true);
    }

    /**
     * Screens that still read posinnova's sales tables (their bus version comes in a later
     * stage). A link to one, from a ready screen, brings the person back with a note
     * instead of an error page.
     */
    public const PENDING = [
        'ledger.ledger-health',
        // E-Ride: the fixed assets (ledger.fixed-assets.*, ledger.fixed-asset-categories.*) work on the bus books
        // (tests/Ledger/fixed_assets_test.php); their menu entries come with STAGE_1.
        // E-Ride: the budgets (ledger.budget.*, ledger.budget-line.*, ledger.budget-sheet.*) work on the bus books, the
        // "department" being the payroll's departments (tests/Ledger/budget_test.php).
        // E-Ride: the Daybook (ledger.daybook.*) and the Accounting reports (ledger.accounting-reports.*) read the
        // bus books (payment accounts, bus money, supplier bills, the posted ledger): tests/Ledger/daybook_reports_test.php.
        // E-Ride: bank reconciliation, recurring entries and year-end closing work on the bus books
        // (tests/Ledger/periodend_test.php); their menu entries come with STAGE_1.
    ];

    public static function isPending(?string $route): bool
    {
        if (! $route || self::isReady($route)) {
            return false;
        }
        foreach (self::PENDING as $p) {
            if ($route === $p || (str_ends_with($p, '.') && str_starts_with($route, $p))) {
                return true;
            }
        }

        return false;
    }
}
