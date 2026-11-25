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
        'ledger.ledger-health', 'ledger.daybook.', 'ledger.budget.', 'ledger.budget-line.', 'ledger.budget-sheet.',
        'ledger.accounting-reports.', 'ledger.fixed-assets.', 'ledger.fixed-asset-categories.', 'ledger.bank-reconciliation.',
        'ledger.recurring-entries.', 'ledger.year-end-closing.',
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
