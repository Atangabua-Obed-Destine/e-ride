<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\BudgetLine;
use App\Ledger\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/**
 * What a budget line or an allocation may point at (paxhitestbed
 * BudgetLineChoices, which listed the categories an income or expense could
 * be recorded against). In PosInnova a line targets a ledger account of its
 * section's class, optionally narrowed by an expense category and a location;
 * this is the one place that lists them, and the rule the controllers check.
 */
class BudgetLineChoices
{
    /**
     * Accounts a line of each section may target: class 7 for income, class 6
     * for expenditure, class 2 (20 to 27) for capital. Headings are offered too:
     * a line on 605 reads 6051 to 6058.
     *
     * @return array<string, array<int, array{id:int, label:string, heading:bool}>>
     */
    public static function accountsBySection(): array
    {
        $out = ['income' => [], 'expenditure' => [], 'capital' => []];
        $rows = ChartOfAccount::whereIn('class_number', [2, 6, 7])->where('is_active', 1)
            ->where('account_code', 'not like', '28%')->where('account_code', 'not like', '29%')
            ->orderBy('account_code')->get();
        foreach ($rows as $a) {
            if (strlen((string) $a->account_code) < 2) {
                continue; // a whole class is not a line
            }
            $section = BudgetActualsService::sectionForClass((int) $a->class_number);
            $out[$section][] = [
                'id' => (int) $a->id,
                'label' => $a->account_code.' '.Preview::accountName($a),
                'heading' => $a->account_category !== 'detail',
            ];
        }

        return $out;
    }

    /** May a line of $section target this account? */
    public static function accountFits(string $section, $accountId): bool
    {
        $a = ChartOfAccount::find($accountId);
        if (! $a) {
            return false;
        }
        $code = (string) $a->account_code;
        if (strlen($code) < 2 || in_array(substr($code, 0, 2), ['28', '29'], true)) {
            return false;
        }

        return (int) $a->class_number === (BudgetLine::SECTION_CLASS[$section] ?? -1);
    }

    /** Spending accounts a departmental allocation may target (classes 6 and 2). */
    public static function spendingAccounts(): array
    {
        $all = self::accountsBySection();

        return array_merge($all['expenditure'], $all['capital']);
    }

    /** This business's expense categories. @return \Illuminate\Support\Collection */
    public static function categories()
    {
        return DB::table('expense_categories')->where('business_id', Ledger::businessId())->whereNull('deleted_at')
            ->orderBy('name')->get(['id', 'name']);
    }

    /** This business's locations (the departments, profit centres). E-Ride: the payroll's departments (posinnova: business_locations). */
    public static function locations()
    {
        return DB::table('departments')->where('business_id', Ledger::businessId())->where('status', 1)
            ->orderBy('title')->get(['id', 'title as name']);
    }

    public static function categoryBelongs($id): bool
    {
        return ! $id || DB::table('expense_categories')->where('business_id', Ledger::businessId())->where('id', $id)->exists();
    }

    public static function locationBelongs($id): bool
    {
        return ! $id || DB::table('departments')->where('business_id', Ledger::businessId())->where('id', $id)->exists(); // E-Ride: departments
    }
}
