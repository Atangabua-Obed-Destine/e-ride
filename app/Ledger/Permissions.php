<?php

namespace App\Ledger;

/**
 * The accounting permissions, with paxhitestbed's names so a role reads the
 * same in both systems. Grouped as on the Roles screen.
 */
class Permissions
{
    const GROUPS = [
        'chart_of_accounts' => ['chart-of-accounts-view', 'chart-of-accounts-create', 'chart-of-accounts-edit', 'chart-of-accounts-delete', 'chart-of-accounts-activate'],
        'fiscal_years' => ['accounting-period-view', 'accounting-period-create', 'accounting-period-edit', 'accounting-period-delete', 'accounting-period-close', 'accounting-period-reopen'],
        'journal_entries' => ['journal-entry-view', 'journal-entry-create', 'journal-entry-edit', 'journal-entry-delete', 'journal-entry-post', 'journal-entry-unpost', 'journal-entry-reverse'],
        'reports' => ['general-ledger-view', 'general-ledger-export', 'trial-balance-view', 'trial-balance-export', 'balance-sheet-view', 'balance-sheet-export',
            'income-statement-view', 'income-statement-export', 'cash-flow-view', 'cash-flow-export', 'accounting-report-view', 'daybook-view'],
        'transaction_mappings' => ['transaction-mapping-view', 'transaction-mapping-manage', 'transaction-mapping-remap', 'transaction-mapping-settings'],
        'budgets' => ['budget-view', 'budget-create', 'budget-edit', 'budget-delete', 'budget-approve', 'budget-activate', 'budget-close', 'budget-cancel',
            'budget-line-view', 'budget-line-create', 'budget-line-edit', 'budget-line-delete'],
        'bank_reconciliation' => ['bank-reconciliation-list', 'bank-reconciliation-create', 'bank-reconciliation-edit', 'bank-reconciliation-delete', 'bank-reconciliation-complete'],
        'recurring_entries' => ['recurring-entry-list', 'recurring-entry-create', 'recurring-entry-edit', 'recurring-entry-delete', 'recurring-entry-process'],
        'year_end_closing' => ['year-end-closing-list', 'year-end-closing-create', 'year-end-closing-edit', 'year-end-closing-delete', 'year-end-closing-approve'],
        'fixed_assets' => ['fixed-asset-list', 'fixed-asset-create', 'fixed-asset-edit', 'fixed-asset-delete'],
    ];

    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /** Label of a permission: its group's thing and the action, e.g. "Journal entries: post". */
    public static function label(string $perm): string
    {
        $action = substr($perm, strrpos($perm, '-') + 1);
        $thing = substr($perm, 0, strrpos($perm, '-'));

        return __('ledger.perm_thing_'.str_replace('-', '_', $thing)).': '.__('ledger.perm_act_'.$action);
    }

    /** Can the signed-in user do any of these? (Admins can do everything.) */
    public static function can(string ...$perms): bool
    {
        $u = auth()->user();
        if (! $u) {
            return false;
        }
        foreach ($perms as $p) {
            if ($u->can($p)) {
                return true;
            }
        }

        return false;
    }
}
