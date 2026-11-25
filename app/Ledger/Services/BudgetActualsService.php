<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\Budget;
use App\Ledger\Models\BudgetAllocation;
use App\Ledger\Models\BudgetLine;
use App\Ledger\Models\DefaultAccountMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What was actually received and spent against each budget line
 * (paxhitestbed BudgetActualsService).
 *
 * paxhitestbed read expenses, incomes and fees from their own tables and only
 * payroll and supplier bills from the ledger. PosInnova reads EVERYTHING from
 * the posted ledger: income is class 7, expenditure class 6, capital class 2
 * (fixed assets 20 to 27; 28 and 29 are depreciation and provisions, whose
 * charge is already in class 6). The POS tables are never read for money,
 * only to say where a ledger line came from: the location and expense
 * category of the document behind the entry. So the sheet agrees with the
 * ledger by construction, and the reconciliation can prove it.
 *
 * Counted: posted entries, not deleted, not the year-end closing entry (which
 * brings classes 6 and 7 back to zero). Reversals are counted: they cancel
 * what they reverse.
 *
 * Two rules matter more than the rest (as in paxhitestbed):
 *   - money is counted once, against exactly one line: the most specific line
 *     that matches it (expense category and location filters first, then the
 *     longest account code);
 *   - money no line matches is reported as unallocated, never dropped.
 */
class BudgetActualsService
{
    /** @var array<int, BudgetLine>|null active postable lines with an account, by id */
    private $lines = null;

    /** @var array<string, int|null> movement key => line id */
    private $routed = [];

    /** @var array<string, Collection> movements already read in this request, by business and period */
    private $memo = [];

    /** Which section a ledger class belongs to. */
    public static function sectionForClass(int $class): ?string
    {
        return [7 => 'income', 6 => 'expenditure', 2 => 'capital'][$class] ?? null;
    }

    /**
     * Ledger movements on the budget classes, grouped by account, location and
     * expense category (and month), in each class's normal direction: income
     * credit minus debit, expenditure and capital debit minus credit.
     *
     * @return Collection<int, object{account_id:int, account_code:string, class_number:int, location_id:?int, expense_category_id:?int, month:?string, amount:float}>
     */
    public function movements(?string $from, ?string $to, bool $byMonth = false): Collection
    {
        $bid = Ledger::businessId();
        $memo = $bid.'|'.$from.'|'.$to.'|'.(int) $byMonth;
        if (isset($this->memo[$memo])) {
            return $this->memo[$memo];
        }

        return $this->memo[$memo] = $this->readMovements($bid, $from, $to, $byMonth);
    }

    private function readMovements(int $bid, ?string $from, ?string $to, bool $byMonth): Collection
    {
        $month = $byMonth ? "DATE_FORMAT(je.entry_date, '%Y-%m')" : 'NULL';
        $rows = DB::select("
            SELECT l.account_id, a.account_code, a.class_number, t.location_id, t.expense_category_id,
                   {$month} AS month, SUM(l.debit) AS debit, SUM(l.credit) AS credit
            FROM journal_entry_lines l
            JOIN journal_entries je ON je.id = l.journal_entry_id
            JOIN chart_of_accounts a ON a.id = l.account_id
            LEFT JOIN (SELECT journal_entry_id, MIN(id) AS id FROM transaction_mappings
                       WHERE business_id = ? AND journal_entry_id IS NOT NULL GROUP BY journal_entry_id) tmx
                   ON tmx.journal_entry_id = je.id
            LEFT JOIN transaction_mappings tm1 ON tm1.id = tmx.id
            LEFT JOIN transaction_mappings tm2 ON je.reference_type = 'transaction_mapping'
                   AND tm2.id = je.reference_id AND tm2.business_id = ?
            LEFT JOIN transaction_payments tp ON tp.id = (CASE
                   WHEN COALESCE(tm1.transaction_type, tm2.transaction_type) = 'payment' THEN COALESCE(tm1.transaction_id, tm2.transaction_id)
                   WHEN tm1.id IS NULL AND tm2.id IS NULL AND je.reference_type = 'payment' THEN je.reference_id END)
            LEFT JOIN transactions t ON t.business_id = ? AND t.id = (CASE
                   WHEN COALESCE(tm1.transaction_type, tm2.transaction_type) = 'payment' THEN tp.transaction_id
                   WHEN COALESCE(tm1.transaction_type, tm2.transaction_type) = 'account_transaction' THEN NULL
                   WHEN COALESCE(tm1.transaction_type, tm2.transaction_type) IS NOT NULL THEN COALESCE(tm1.transaction_id, tm2.transaction_id)
                   WHEN je.reference_type = 'transaction' THEN je.reference_id
                   WHEN je.reference_type = 'payment' THEN tp.transaction_id END)
            WHERE l.business_id = ? AND je.business_id = ? AND a.business_id = ?
              AND je.is_posted = 1 AND je.deleted_at IS NULL AND je.journal_type <> 'closing'
              AND (a.class_number IN (6, 7) OR (a.class_number = 2 AND a.account_code NOT LIKE '28%' AND a.account_code NOT LIKE '29%'))
              AND je.entry_date >= ? AND je.entry_date <= ?
            GROUP BY l.account_id, a.account_code, a.class_number, t.location_id, t.expense_category_id".($byMonth ? ', month' : ''),
            [$bid, $bid, $bid, $bid, $bid, $bid, $from ?: '0001-01-01', $to ?: '9999-12-31']);

        return collect($rows)->map(function ($r) {
            $class = (int) $r->class_number;
            $r->amount = round($class === 7 ? (float) $r->credit - (float) $r->debit : (float) $r->debit - (float) $r->credit, 2);
            $r->class_number = $class;
            $r->account_id = (int) $r->account_id;
            $r->location_id = $r->location_id !== null ? (int) $r->location_id : null;
            $r->expense_category_id = $r->expense_category_id !== null ? (int) $r->expense_category_id : null;

            return $r;
        })->filter(fn ($r) => abs($r->amount) > 0.004)->values();
    }

    /**
     * Actual amounts keyed by budget line id, for a date range.
     *
     * @return array{lines: array<int,float>, unallocated: array<string,float>}
     */
    public function forPeriod(?string $from = null, ?string $to = null): array
    {
        $lines = [];
        $unallocated = [];
        foreach ($this->movements($from, $to) as $mv) {
            $id = $this->lineFor($mv);
            if ($id === null) {
                $section = self::sectionForClass($mv->class_number);
                $unallocated[$section] = round(($unallocated[$section] ?? 0) + $mv->amount, 2);
                continue;
            }
            $lines[$id] = round(($lines[$id] ?? 0) + $mv->amount, 2);
        }

        return ['lines' => $lines, 'unallocated' => array_filter($unallocated, fn ($v) => abs($v) > 0.004)];
    }

    /** Actuals by line and month ('Y-m'), for the cash-flow projection. @return array<int, array<string, float>> */
    public function byLineAndMonth(?string $from, ?string $to): array
    {
        $out = [];
        foreach ($this->movements($from, $to, true) as $mv) {
            $id = $this->lineFor($mv);
            if ($id !== null) {
                $out[$id][$mv->month] = round(($out[$id][$mv->month] ?? 0) + $mv->amount, 2);
            }
        }

        return $out;
    }

    /**
     * The line a movement lands on: active, not a heading, in the section of the
     * movement's class, targeting the movement's account or an account above it,
     * and whose expense category / location filters (when set) match. The most
     * specific wins: more filters, then the longer account code, then the
     * earlier line on the sheet.
     */
    public function lineFor(object $mv): ?int
    {
        $key = $mv->account_id.'|'.($mv->location_id ?? '').'|'.($mv->expense_category_id ?? '');
        if (array_key_exists($key, $this->routed)) {
            return $this->routed[$key];
        }
        $section = self::sectionForClass((int) $mv->class_number);
        $best = null;
        $bestScore = null;
        foreach ($this->lines() as $line) {
            if ($line->section !== $section || ! $line->account) {
                continue;
            }
            $code = (string) $line->account->account_code;
            if ($code === '' || strpos((string) $mv->account_code, $code) !== 0) {
                continue;
            }
            if ($line->expense_category_id && (int) $line->expense_category_id !== (int) $mv->expense_category_id) {
                continue;
            }
            if ($line->location_id && (int) $line->location_id !== (int) $mv->location_id) {
                continue;
            }
            $score = [($line->expense_category_id ? 2 : 0) + ($line->location_id ? 1 : 0), strlen($code), -$line->sort_order, -$line->id];
            if ($bestScore === null || $score > $bestScore) {
                $best = $line;
                $bestScore = $score;
            }
        }

        return $this->routed[$key] = $best ? (int) $best->id : null;
    }

    /** @return array<int, BudgetLine> */
    public function lines(): array
    {
        if ($this->lines === null) {
            $this->lines = BudgetLine::active()->postable()->whereNotNull('account_id')->with('account')
                ->get()->keyBy('id')->all();
        }

        return $this->lines;
    }

    /** Forget the cached lines (after a line was added, retired or retargeted). */
    public function forget(): void
    {
        $this->lines = null;
        $this->routed = [];
        $this->memo = [];
    }

    /**
     * Roll child figures up into their headings, so a group shows the sum of the
     * lines beneath it without those lines being counted twice.
     *
     * @param  array<int,float>  $lineTotals
     * @return array<int,float>
     */
    public function withHeaderTotals(array $lineTotals): array
    {
        $with = $lineTotals;
        $children = BudgetLine::where('is_header', false)->whereNotNull('parent_id')->get(['id', 'parent_id'])->groupBy('parent_id');
        foreach (BudgetLine::where('is_header', true)->pluck('id') as $headerId) {
            $sum = 0.0;
            foreach ($children->get($headerId, collect()) as $child) {
                $sum += $lineTotals[$child->id] ?? 0;
            }
            $with[$headerId] = round($sum, 2);
        }

        return $with;
    }

    /** A section's total. Headings are left out so their lines are not counted twice. */
    public function sectionTotal(array $lineTotals, string $section): float
    {
        $total = 0.0;
        foreach (BudgetLine::where('section', $section)->where('is_header', false)->pluck('id') as $id) {
            $total += $lineTotals[$id] ?? 0;
        }

        return round($total, 2);
    }

    // ------------------------------------------------------------ one month against the budget

    /**
     * The Daybook Monthly Summary's budget columns (paxhitestbed's summary rows
     * are the Income & Expenditure sheet's lines): for each line of the sheet,
     * the year's Budget, this month's actual (Monthly), everything since the
     * budget opened up to the end of last month (BB Forward), their sum
     * (Cumulative) and what is left (Balance = Budget - Cumulative).
     *
     * Actuals are the sheet's own (forPeriod, from the posted ledger), so at the
     * budget's end Cumulative equals the sheet. The month is clipped to the
     * budget's dates; a month outside the budget returns null.
     *
     * @return array{budget: Budget, rows: array, totals: array, unallocated: array}|null
     */
    public function monthAgainstBudget(Budget $budget, string $monthStart, string $monthEnd): ?array
    {
        $bStart = $budget->start_date->format('Y-m-d');
        $bEnd = $budget->end_date->format('Y-m-d');
        if ($monthEnd < $bStart || $monthStart > $bEnd) {
            return null;
        }
        $from = max($monthStart, $bStart);
        $to = min($monthEnd, $bEnd);
        $priorEnd = date('Y-m-d', strtotime($from.' -1 day'));

        $budgeted = BudgetAllocation::where('budget_id', $budget->id)->whereNotNull('budget_line_id')
            ->pluck('allocated_amount', 'budget_line_id')->map(fn ($v) => (float) $v)->toArray();
        $month = $this->forPeriod($from, $to);
        $brought = $priorEnd >= $bStart ? $this->forPeriod($bStart, $priorEnd) : ['lines' => [], 'unallocated' => []];

        $lines = BudgetLine::sheet();
        $ends = BudgetLine::groupEnds($lines);
        $b = $this->withHeaderTotals($budgeted);
        $m = $this->withHeaderTotals($month['lines']);
        $f = $this->withHeaderTotals($brought['lines']);
        $row = function (BudgetLine $line, string $kind) use ($b, $m, $f) {
            $has = array_key_exists($line->id, $b);
            $cum = round(($m[$line->id] ?? 0) + ($f[$line->id] ?? 0), 2);

            return [
                'id' => (int) $line->id, 'code' => (string) $line->code, 'name' => $line->display_name,
                'section' => $line->section, 'kind' => $kind,
                'budget' => $has ? round($b[$line->id], 2) : null,
                'monthly' => round($m[$line->id] ?? 0, 2),
                'brought' => round($f[$line->id] ?? 0, 2),
                'cumulative' => $cum,
                'balance' => $has ? round($b[$line->id] - $cum, 2) : null,
            ];
        };

        $rows = [];
        foreach ($lines as $line) {
            $rows[] = $row($line, $line->is_header ? 'header' : ($line->parent_id ? 'child' : 'line'));
            if (isset($ends[$line->id])) {
                $rows[] = $row($ends[$line->id], 'subtotal');
            }
        }

        $totals = [];
        foreach (BudgetLine::SECTIONS as $s) {
            $bud = $this->sectionTotal($budgeted, $s);
            $cum = round($this->sectionTotal($month['lines'], $s) + $this->sectionTotal($brought['lines'], $s), 2);
            $totals[$s] = [
                'budget' => $bud,
                'monthly' => $this->sectionTotal($month['lines'], $s),
                'brought' => $this->sectionTotal($brought['lines'], $s),
                'cumulative' => $cum,
                'balance' => round($bud - $cum, 2),
            ];
        }
        $unallocated = [];
        foreach (BudgetLine::SECTIONS as $s) {
            $um = (float) ($month['unallocated'][$s] ?? 0);
            $ub = (float) ($brought['unallocated'][$s] ?? 0);
            if (abs($um) > 0.004 || abs($ub) > 0.004) {
                $unallocated[$s] = ['monthly' => round($um, 2), 'brought' => round($ub, 2), 'cumulative' => round($um + $ub, 2)];
            }
        }

        return ['budget' => $budget, 'from' => $from, 'to' => $to, 'rows' => $rows, 'totals' => $totals, 'unallocated' => $unallocated];
    }

    // ------------------------------------------------------------ budgets' spend

    /**
     * Write the ledger's actuals onto a budget's allocations and totals
     * (paxhitestbed syncSpentAmounts and updateSpentAmount: recomputed from the
     * source, never patched). Only rows whose figures moved are written.
     */
    public function refresh(Budget $budget): void
    {
        $from = optional($budget->start_date)->format('Y-m-d');
        $to = optional($budget->end_date)->format('Y-m-d');
        $allocations = BudgetAllocation::where('budget_id', $budget->id)->get();

        if ($budget->is_institutional) {
            $actual = $this->forPeriod($from, $to)['lines'];
            $spending = BudgetLine::whereIn('section', ['expenditure', 'capital'])->where('is_header', false)->pluck('id')->flip();
            $allocated = 0.0;
            $spent = 0.0;
            foreach ($allocations as $a) {
                $s = (float) ($actual[$a->budget_line_id] ?? 0);
                $this->writeSpent($a, $s);
                // The budget-level totals count money going OUT only, so a healthy
                // sheet is not reported at 948% because income was summed in.
                if ($a->budget_line_id && $spending->has($a->budget_line_id)) {
                    $allocated += (float) $a->allocated_amount;
                    $spent += $s;
                }
            }
            $this->writeBudget($budget, ['total_amount' => round($allocated, 2), 'allocated_amount' => round($allocated, 2),
                'spent_amount' => round($spent, 2), 'remaining_amount' => round($allocated - $spent, 2)]);

            return;
        }

        $spentBy = $this->allocationActuals($budget, $allocations, $from, $to);
        $spent = 0.0;
        foreach ($allocations as $a) {
            $s = (float) ($spentBy[$a->id] ?? 0);
            $spent += $s;
            $this->writeSpent($a, $s);
        }
        $this->writeBudget($budget, ['allocated_amount' => round((float) $allocations->sum('allocated_amount'), 2),
            'spent_amount' => round($spent, 2), 'remaining_amount' => round((float) $budget->total_amount - $spent, 2)]);
    }

    /**
     * What the ledger holds against each allocation of a departmental budget,
     * keyed by allocation id (and month, with $byMonth). Spending only (classes
     * 6 and 2); each movement goes to the most specific allocation it matches,
     * so two allocations never count the same money.
     */
    public function allocationActuals(Budget $budget, $allocations, ?string $from, ?string $to, bool $byMonth = false): array
    {
        $targets = [];
        foreach ($allocations as $a) {
            if (! $a->is_active || (! $a->account_id && ! $a->expense_category_id)) {
                continue;
            }
            $targets[] = [
                'id' => (int) $a->id,
                'code' => $a->account_id ? (string) optional($a->account)->account_code : null,
                'category' => $a->expense_category_id ? (int) $a->expense_category_id : null,
                'location' => ($a->location_id ?: $budget->location_id) ? (int) ($a->location_id ?: $budget->location_id) : null,
            ];
        }
        $out = [];
        if (! $targets) {
            return $out;
        }
        foreach ($this->movements($from, $to, $byMonth) as $mv) {
            if (! in_array($mv->class_number, [6, 2], true)) {
                continue;
            }
            $best = null;
            $bestScore = null;
            foreach ($targets as $t) {
                if ($t['code'] !== null && ($t['code'] === '' || strpos((string) $mv->account_code, $t['code']) !== 0)) {
                    continue;
                }
                if ($t['code'] === null && $mv->class_number !== 6) {
                    continue; // a category alone means its expenses (class 6)
                }
                if ($t['category'] && $t['category'] !== $mv->expense_category_id) {
                    continue;
                }
                if ($t['location'] && $t['location'] !== $mv->location_id) {
                    continue;
                }
                $score = [($t['category'] ? 1 : 0) + ($t['code'] !== null ? 1 : 0) + ($t['location'] ? 1 : 0), strlen((string) $t['code']), -$t['id']];
                if ($bestScore === null || $score > $bestScore) {
                    $best = $t['id'];
                    $bestScore = $score;
                }
            }
            if ($best === null) {
                continue;
            }
            if ($byMonth) {
                $out[$best][$mv->month] = round(($out[$best][$mv->month] ?? 0) + $mv->amount, 2);
            } else {
                $out[$best] = round(($out[$best] ?? 0) + $mv->amount, 2);
            }
        }

        return $out;
    }

    /**
     * The sheet line a departmental allocation's money lands on: its account
     * (or, for a category alone, the account the category posts to), with its
     * category and location. Used to show how much of a sheet line is delegated.
     */
    public function lineForAllocation(BudgetAllocation $a, ?int $budgetLocation): ?int
    {
        $accountId = $a->account_id ?: ($a->expense_category_id
            ? DefaultAccountMapping::accountFor('expense_category', (string) $a->expense_category_id) : null);
        if (! $accountId) {
            return null;
        }
        $acc = \App\Ledger\Models\ChartOfAccount::find($accountId);
        if (! $acc) {
            return null;
        }

        return $this->lineFor((object) [
            'account_id' => (int) $acc->id, 'account_code' => (string) $acc->account_code, 'class_number' => (int) $acc->class_number,
            'location_id' => $a->location_id ?: $budgetLocation, 'expense_category_id' => $a->expense_category_id,
        ]);
    }

    private function writeSpent(BudgetAllocation $a, float $spent): void
    {
        $remaining = round((float) $a->allocated_amount - $spent, 2);
        if (abs((float) $a->spent_amount - $spent) < 0.01 && abs((float) $a->remaining_amount - $remaining) < 0.01) {
            return;
        }
        $a->forceFill(['spent_amount' => $spent, 'remaining_amount' => $remaining])->save();
    }

    private function writeBudget(Budget $b, array $values): void
    {
        foreach ($values as $k => $v) {
            if (abs((float) $b->$k - (float) $v) >= 0.01) {
                $b->forceFill($values)->save();

                return;
            }
        }
    }
}
