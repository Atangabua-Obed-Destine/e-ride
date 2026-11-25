<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Opening a business's books: the SYSCOHADA chart, the current fiscal year
 * (1 January to 31 December, as OHADA requires) with its twelve months, and
 * the default account mappings. Create-only and safe to run again: an
 * account, year or mapping that exists is left exactly as it is (the rule of
 * paxhitestbed's SeedsWithoutOverwriting).
 */
class LedgerSetup
{
    public static function install(int $businessId, ?string $userId = null, ?int $year = null): array
    {
        return Ledger::forBusiness($businessId, function () use ($businessId, $userId, $year) {
            return DB::transaction(function () use ($businessId, $userId, $year) {
                $made = ['accounts' => 0, 'periods' => 0, 'mappings' => 0];

                // 1. Chart of accounts: create what is missing, then link parents of what we created.
                $ids = ChartOfAccount::withTrashed()->pluck('id', 'account_code')->all();
                $fresh = [];
                $order = 0;
                foreach (SyscohadaChart::accounts() as [$code, $name, $fr, $parent, $type, $normal, $cat]) {
                    $order++;
                    if (isset($ids[$code])) {
                        continue;
                    }
                    $acc = ChartOfAccount::create([
                        'business_id' => $businessId,
                        'account_code' => $code,
                        'account_name' => $name,
                        'account_name_fr' => $fr,
                        'class_number' => (int) $code[0],
                        'account_type' => $type,
                        'account_category' => $cat === 'h' ? 'heading' : 'detail',
                        'cash_flow_category' => SyscohadaChart::cashFlowCategory($code),
                        'normal_balance' => $normal,
                        'is_active' => true,
                        'is_system' => strlen($code) <= 2 || $cat === 'h',
                        'display_order' => $order,
                        'created_by' => $userId,
                    ]);
                    $ids[$code] = $acc->id;
                    $fresh[$code] = $parent;
                    $made['accounts']++;
                }
                foreach ($fresh as $code => $parent) {
                    if ($parent && isset($ids[$parent])) {
                        ChartOfAccount::where('id', $ids[$code])->update(['parent_id' => $ids[$parent]]);
                    }
                }

                // 2. The fiscal year of today (or $year) and its months.
                $y = $year ?: (int) Carbon::today()->year;
                $start = Carbon::create($y, 1, 1)->toDateString();
                $fy = FiscalYear::forDate($start);
                if (! $fy) {
                    $fy = FiscalYear::create([
                        'business_id' => $businessId,
                        'name' => (string) $y,
                        'start_date' => $start,
                        'end_date' => Carbon::create($y, 12, 31)->toDateString(),
                        'is_active' => true,
                        'created_by' => $userId,
                    ]);
                }
                if ($fy->accountingPeriods()->count() === 0) {
                    $made['periods'] = $fy->generatePeriods($userId);
                }

                // 3. Default mappings and the cash side of each payment method.
                $have = DefaultAccountMapping::get()->map(fn ($m) => $m->mapping_type.'|'.$m->category_key)->flip();
                $add = function ($type, $key, $code) use ($have, $ids, $businessId, $userId, &$made) {
                    if (isset($have[$type.'|'.$key]) || ! isset($ids[$code])) {
                        return;
                    }
                    DefaultAccountMapping::create([
                        'business_id' => $businessId, 'mapping_type' => $type, 'category_key' => $key,
                        'debit_account_id' => $ids[$code], 'credit_account_id' => $ids[$code],
                        'status' => 1, 'created_by' => $userId,
                    ]);
                    $made['mappings']++;
                };
                foreach (SyscohadaChart::defaultMappings() as $type => $code) {
                    $add($type, '', $code);
                }
                foreach (SyscohadaChart::defaultPaymentMethods() as $method => $code) {
                    $add('payment_method', $method, $code);
                }

                if (AccountingSetting::getValue('books_opened_at') === null) {
                    AccountingSetting::setValue('books_opened_at', Carbon::now()->toDateTimeString());
                    // Documents dated before this are history: the sync does not post them unless asked.
                    AccountingSetting::setValue('post_from_date', Carbon::today()->toDateString());
                }

                return $made;
            });
        });
    }
}
