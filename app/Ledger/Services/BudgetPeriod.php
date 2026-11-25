<?php

namespace App\Ledger\Services;

use App\Ledger\Models\Budget;
use App\Ledger\Models\FiscalYear;
use Carbon\Carbon;
use Closure;

/**
 * What dates a budget may cover, and whether they line up with the exercices
 * (paxhitestbed BudgetPeriod).
 *
 * Overlap is refused outright: two sheets covering the same day would each
 * claim the same income and the same spending. Alignment is reported as a
 * warning rather than refused: drafting next year's sheet before its fiscal
 * year exists is ordinary.
 */
class BudgetPeriod
{
    /** No two sheets of this business may cover the same day (departmental budgets are exempt). */
    public static function noOverlapRule(?string $startDate, ?int $ignoreId = null): Closure
    {
        return function ($attribute, $value, $fail) use ($startDate, $ignoreId) {
            if (! $startDate || ! $value) {
                return;
            }
            $clash = Budget::where('is_institutional', true)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('status', '!=', 'cancelled')
                ->whereDate('start_date', '<=', $value)
                ->whereDate('end_date', '>=', $startDate)
                ->first();
            if ($clash) {
                $fail(__('ledger_budget.err_overlap', [
                    'title' => $clash->title,
                    'from' => $clash->start_date->format('d/m/Y'),
                    'to' => $clash->end_date->format('d/m/Y'),
                ]));
            }
        };
    }

    /** A delegated budget belongs inside the sheet it was delegated from. */
    public static function withinParentRule(?string $startDate, $parentId): Closure
    {
        return function ($attribute, $value, $fail) use ($startDate, $parentId) {
            if (! $parentId || ! $startDate || ! $value) {
                return;
            }
            $parent = Budget::find($parentId);
            if (! $parent || ! $parent->start_date || ! $parent->end_date) {
                return;
            }
            if (Carbon::parse($startDate)->lt($parent->start_date) || Carbon::parse($value)->gt($parent->end_date)) {
                $fail(__('ledger_budget.err_outside_parent', [
                    'title' => $parent->title,
                    'from' => $parent->start_date->format('d/m/Y'),
                    'to' => $parent->end_date->format('d/m/Y'),
                ]));
            }
        };
    }

    /** How this period sits against the exercices, said plainly, not enforced. @return string[] */
    public static function alignmentWarnings(?string $startDate, ?string $endDate): array
    {
        if (! $startDate || ! $endDate) {
            return [];
        }
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $startYear = FiscalYear::forDate($start);
        $endYear = FiscalYear::forDate($end);

        if (! $startYear && ! $endYear) {
            return [__('ledger_budget.warn_no_fiscal_year')];
        }
        $warnings = [];
        if (! $startYear) {
            $warnings[] = __('ledger_budget.warn_starts_before', ['date' => $start->format('d/m/Y')]);
        }
        if (! $endYear) {
            $warnings[] = __('ledger_budget.warn_ends_after', ['date' => $end->format('d/m/Y')]);
        }
        // Counted by overlap, not by the two endpoints: a period starting before
        // any exercice still spans the ones it reaches.
        $spanned = FiscalYear::whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')->pluck('name');
        if ($spanned->count() > 1) {
            $warnings[] = __('ledger_budget.warn_spans', ['years' => $spanned->implode(', ')]);
        }
        if ($startYear && $endYear && $startYear->id === $endYear->id) {
            $exact = $start->isSameDay($startYear->start_date) && $end->isSameDay($startYear->end_date);
            if (! $exact) {
                $warnings[] = __('ledger_budget.warn_inside', [
                    'year' => $startYear->name,
                    'from' => $startYear->start_date->format('d/m/Y'),
                    'to' => $startYear->end_date->format('d/m/Y'),
                ]);
            }
        }

        return $warnings;
    }

    /** The fiscal_year label, derived from both dates: "2026", or "2026-27" for a period across two years. */
    public static function labelFor(?string $startDate, ?string $endDate): string
    {
        if (! $startDate) {
            return (string) date('Y');
        }
        $start = Carbon::parse($startDate);
        if (! $endDate) {
            return (string) $start->year;
        }
        $end = Carbon::parse($endDate);
        if ($start->year === $end->year) {
            return (string) $start->year;
        }

        return $start->year.'-'.substr((string) $end->year, -2);
    }
}
