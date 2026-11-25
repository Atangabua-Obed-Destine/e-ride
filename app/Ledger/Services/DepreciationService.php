<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingPeriod;
use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DepreciationSchedule;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\FixedAsset;
use App\Ledger\Models\FixedAssetCategory;
use App\Ledger\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Depreciation, disposal and the asset reports (paxhitestbed
 * App\Services\Accounting\DepreciationService), for the current business.
 * Every entry goes through LedgerPoster.
 *
 * Fixed here as in paxhitestbed's corrected copy: a month is charged once
 * (one schedule per asset per month; running the month again charges
 * nothing), a reversed month can be charged again (on its own row), the
 * month after 31 January is February (startOfMonth()->addMonthNoOverflow()),
 * accounts are found by account_code, and a disposal entry is a real,
 * numbered, balanced entry in the month of the disposal.
 */
class DepreciationService
{
    /**
     * Charge (and, with $autoPost, post) one month's depreciation of every
     * depreciable asset. $date is any day of the month.
     */
    public function calculateMonthlyDepreciation($date = null, $autoPost = false, ?string $userId = null)
    {
        $date = Carbon::parse($date ?: now())->endOfMonth()->startOfDay();
        $userId = $userId ?? auth()->id();
        $results = ['processed' => 0, 'posted' => 0, 'skipped' => 0, 'errors' => [], 'schedules' => []];

        // The year the depreciation DATE falls in, not whichever is active: a
        // December run done in January belongs to December's year.
        $fiscalYear = FiscalYear::forDate($date->toDateString());
        if (! $fiscalYear) {
            throw new \RuntimeException(__('ledger_assets.no_fiscal_year_covers', ['date' => $date->format('d/m/Y')]));
        }
        $period = FiscalYear::periodForDate($date->toDateString());

        foreach (FixedAsset::depreciable()->orderBy('asset_code')->get() as $asset) {
            try {
                // Already charged (or waiting to be posted) for this month:
                // running the month twice must not charge twice.
                $existing = DepreciationSchedule::where('fixed_asset_id', $asset->id)
                    ->whereDate('depreciation_date', $date->toDateString())->first();
                if ($existing && $existing->status !== DepreciationSchedule::STATUS_REVERSED) {
                    if ($autoPost && $existing->status === DepreciationSchedule::STATUS_PENDING) {
                        $existing->post($userId);
                        $results['posted']++;
                    } else {
                        $results['skipped']++;
                    }
                    continue;
                }

                if (! $asset->isDepreciationDue($date)) {
                    $results['skipped']++;
                    continue;
                }

                $amount = $asset->calculateMonthlyDepreciation($date);
                if ($amount <= 0) {
                    $results['skipped']++;
                    continue;
                }

                $schedule = $this->createDepreciationSchedule($asset, $amount, $date->copy(), $fiscalYear, $period, $existing, $userId);
                $results['schedules'][] = $schedule;
                $results['processed']++;

                if ($autoPost) {
                    $schedule->post($userId);
                    $results['posted']++;
                }
            } catch (\Throwable $e) {
                $results['errors'][] = ['asset_id' => $asset->id, 'asset_code' => $asset->asset_code, 'error' => $e->getMessage()];
                Log::warning('Depreciation of '.$asset->asset_code.' failed: '.$e->getMessage());
            }
        }

        return $results;
    }

    protected function createDepreciationSchedule(FixedAsset $asset, float $amount, Carbon $date, FiscalYear $fiscalYear,
        ?AccountingPeriod $period = null, ?DepreciationSchedule $reversed = null, ?string $userId = null): DepreciationSchedule
    {
        $values = [
            'fixed_asset_id' => $asset->id,
            'depreciation_date' => $date->toDateString(),
            'depreciation_amount' => $amount,
            'accumulated_depreciation_before' => (float) $asset->accumulated_depreciation,
            'accumulated_depreciation_after' => round((float) $asset->accumulated_depreciation + $amount, 2),
            'book_value_before' => (float) $asset->book_value,
            'book_value_after' => round((float) $asset->book_value - $amount, 2),
            'fiscal_year_id' => $fiscalYear->id,
            'accounting_period_id' => $period->id ?? null,
            'status' => DepreciationSchedule::STATUS_PENDING,
            'created_by' => $userId,
        ];

        // A month whose charge was reversed is charged again on the same row.
        if ($reversed) {
            $reversed->fill($values + ['journal_entry_id' => null, 'posted_at' => null, 'posted_by' => null])->save();

            return $reversed;
        }

        return DepreciationSchedule::create($values);
    }

    /** Post every pending month (of one accounting period, or all). All or nothing. */
    public function postPendingDepreciation(?int $accountingPeriodId = null, ?string $userId = null)
    {
        $results = ['posted' => 0, 'errors' => []];
        $query = DepreciationSchedule::pending()->orderBy('depreciation_date');
        if ($accountingPeriodId) {
            $query->forPeriod($accountingPeriodId);
        }

        DB::transaction(function () use ($query, &$results, $userId) {
            foreach ($query->get() as $schedule) {
                $schedule->post($userId ?? auth()->id());
                $results['posted']++;
            }
        });

        return $results;
    }

    /** The months ahead, as the asset stands now. */
    public function generateForecast(FixedAsset $asset, int $months = 12)
    {
        $forecast = [];
        $book = (float) $asset->book_value;
        $accumulated = (float) $asset->accumulated_depreciation;
        $month = $asset->nextDueMonth();

        for ($i = 0; $i < $months; $i++) {
            if ($book <= (float) $asset->salvage_value) {
                break;
            }
            $probe = clone $asset;
            $probe->book_value = $book;
            $probe->accumulated_depreciation = $accumulated;
            $amount = $probe->calculateMonthlyDepreciation($month);
            if ($amount <= 0) {
                break;
            }
            $accumulated += $amount;
            $book = max($book - $amount, (float) $asset->salvage_value);
            $forecast[] = ['period' => $month->format('Y-m'), 'depreciation_amount' => $amount, 'accumulated_depreciation' => $accumulated, 'book_value' => $book];
            $month = $month->copy()->addMonthNoOverflow();
        }

        return $forecast;
    }

    /**
     * Per category: how many assets are held, their cost, accumulated
     * depreciation and book value now, and the depreciation posted in the
     * year. (paxhitestbed summed the assets' cost once per schedule row, so a
     * category's cost grew with every month charged, and the view read keys
     * the query did not return.)
     */
    public function getSummaryByCategory(int $fiscalYearId): array
    {
        $bid = Ledger::businessId();
        $held = DB::table('fixed_assets')->where('business_id', $bid)->whereNull('deleted_at')
            ->where('status', '!=', FixedAsset::STATUS_DISPOSED)
            ->groupBy('category_id')
            ->selectRaw('category_id, COUNT(*) as n, SUM(acquisition_cost) as cost, SUM(accumulated_depreciation) as acc, SUM(book_value) as book')
            ->get()->keyBy('category_id');
        $year = DB::table('depreciation_schedules as s')->join('fixed_assets as a', 'a.id', '=', 's.fixed_asset_id')
            ->where('s.business_id', $bid)->where('s.fiscal_year_id', $fiscalYearId)->where('s.status', DepreciationSchedule::STATUS_POSTED)
            ->groupBy('a.category_id')->selectRaw('a.category_id, SUM(s.depreciation_amount) as dep')
            ->pluck('dep', 'category_id');

        $out = [];
        foreach (FixedAssetCategory::withTrashed()->orderBy('name')->get() as $c) {
            $h = $held->get($c->id);
            if (! $h && ! isset($year[$c->id])) {
                continue;
            }
            $out[] = [
                'category_id' => $c->id,
                'category_name' => $c->name,
                'code' => $c->code,
                'asset_count' => (int) ($h->n ?? 0),
                'total_acquisition_cost' => round((float) ($h->cost ?? 0), 2),
                'depreciation_this_year' => round((float) ($year[$c->id] ?? 0), 2),
                'accumulated_depreciation' => round((float) ($h->acc ?? 0), 2),
                'net_book_value' => round((float) ($h->book ?? 0), 2),
            ];
        }

        return $out;
    }

    /**
     * Take an asset out of the books:
     *   Dr 28x accumulated depreciation   (what was charged)
     *   Dr 5x  cash or bank               (the proceeds, if any)
     *   Cr 2x  the asset at cost
     *   and the difference: Cr 82x gain / Dr 81x loss (or the category's disposal account).
     */
    public function disposeAsset(FixedAsset $asset, $disposalDate, float $disposalValue, ?string $reason = null,
        ?int $paymentAccountId = null, ?string $method = null, ?string $userId = null): JournalEntry
    {
        $disposalDate = Carbon::parse($disposalDate);
        $userId = $userId ?? auth()->id();
        $category = $asset->category;

        if ($asset->status === FixedAsset::STATUS_DISPOSED) {
            throw new \RuntimeException(__('ledger_assets.already_disposed'));
        }
        if ($disposalDate->lt(Carbon::parse($asset->acquisition_date)->startOfDay())) {
            throw new \RuntimeException(__('ledger_assets.disposal_before_acquisition', ['date' => Carbon::parse($asset->acquisition_date)->format('d/m/Y')]));
        }
        if (! $category || ! $category->asset_account_id || ! $category->accumulated_depreciation_account_id) {
            throw new \RuntimeException(__('ledger_assets.asset_accounts_not_configured', ['category' => $category->name ?? '']));
        }

        $lines = [];
        $accumulated = round((float) $asset->accumulated_depreciation, 2);
        if ($accumulated > 0) {
            $lines[] = [$category->accumulated_depreciation_account_id, $accumulated, 0, __('ledger_assets.Accumulated depreciation removed')];
        }
        if ($disposalValue > 0) {
            $cashAccountId = $this->cashAccountFor($paymentAccountId) ?? AccountingSetting::getValue('default_cash_account_id');
            if (! $cashAccountId) {
                throw new \RuntimeException(__('ledger_assets.choose_paid_into'));
            }
            $lines[] = [(int) $cashAccountId, round($disposalValue, 2), 0, __('ledger_assets.Disposal proceeds')];
        }
        $lines[] = [$category->asset_account_id, 0, round((float) $asset->acquisition_cost, 2), __('ledger_assets.Asset removed at cost')];

        $gainLoss = round($disposalValue - ((float) $asset->acquisition_cost - $accumulated), 2);
        if ($gainLoss != 0) {
            $accountId = $category->disposal_account_id ?: $this->firstDetail($gainLoss > 0 ? '82' : '81');
            if (! $accountId) {
                throw new \RuntimeException(__('ledger_assets.no_disposal_account', [
                    'code' => $gainLoss > 0 ? '82' : '81', 'what' => $gainLoss > 0 ? __('ledger_assets.gain') : __('ledger_assets.loss')]));
            }
            $lines[] = $gainLoss > 0
                ? [$accountId, 0, $gainLoss, __('ledger_assets.Gain on disposal')]
                : [$accountId, abs($gainLoss), 0, __('ledger_assets.Loss on disposal')];
        }

        return DB::transaction(function () use ($asset, $disposalDate, $disposalValue, $reason, $method, $userId, $lines) {
            $entry = LedgerPoster::post([
                'entry_date' => $disposalDate->toDateString(),
                'journal_type' => 'adjustment',
                'description' => __('ledger_assets.disposal_of_asset').' '.$asset->name.($reason ? ' - '.$reason : ''),
                'reference_number' => 'DISP-'.$asset->asset_code,
                'reference_type' => 'asset_disposal',
                'reference_id' => $asset->id,
                'is_system_generated' => true,
            ], array_map(fn ($l) => ['account_id' => $l[0], 'debit' => $l[1], 'credit' => $l[2], 'description' => $l[3].' — '.$asset->name], $lines), $userId);

            // a month calculated but not posted no longer applies
            DepreciationSchedule::where('fixed_asset_id', $asset->id)->where('status', DepreciationSchedule::STATUS_PENDING)->delete();
            $asset->updated_by = $userId;
            $asset->dispose($disposalDate->toDateString(), $disposalValue, $reason, $entry->id, $method);

            return $entry;
        });
    }

    /**
     * The purchase of the asset in the ledger: Dr the category's asset account
     * (2x), Cr the cash box or bank it was paid from, or 401 Suppliers when it
     * is still owed. Dated on the acquisition date. (paxhitestbed's store()
     * did this on request, crediting the first 52x account.)
     */
    public function postPurchase(FixedAsset $asset, $paidFrom, ?string $userId = null): JournalEntry
    {
        $userId = $userId ?? auth()->id();
        if ($asset->purchase_journal_entry_id) {
            throw new \RuntimeException(__('ledger_assets.purchase_already_posted'));
        }
        $category = $asset->category;
        if (! $category || ! $category->asset_account_id) {
            throw new \RuntimeException(__('ledger_assets.asset_accounts_not_configured', ['category' => $category->name ?? '']));
        }
        if ($paidFrom === 'supplier') {
            $credit = DefaultAccount::code('401');
            $text = __('ledger_assets.owed_to_supplier');
        } else {
            $credit = $this->cashAccountFor((int) $paidFrom);
            $text = __('ledger_assets.payment_for_fixed_asset');
        }
        if (! $credit) {
            throw new \RuntimeException(__('ledger_assets.choose_paid_from'));
        }
        $cost = round((float) $asset->acquisition_cost, 2);

        return DB::transaction(function () use ($asset, $category, $credit, $text, $cost, $userId) {
            $entry = LedgerPoster::post([
                'entry_date' => Carbon::parse($asset->acquisition_date)->toDateString(),
                'journal_type' => 'purchase',
                'description' => __('ledger_assets.purchase_of_fixed_asset').' - '.$asset->name,
                'reference_number' => 'FA-'.$asset->asset_code,
                'reference_type' => 'fixed_asset',
                'reference_id' => $asset->id,
                'is_system_generated' => true,
            ], [
                ['account_id' => $category->asset_account_id, 'debit' => $cost, 'credit' => 0, 'description' => __('ledger_assets.fixed_asset_acquisition').' — '.$asset->name],
                ['account_id' => $credit, 'debit' => 0, 'credit' => $cost, 'description' => $text.' — '.$asset->name],
            ], $userId);
            $asset->forceFill(['purchase_journal_entry_id' => $entry->id, 'updated_by' => $userId])->save();

            return $entry;
        });
    }

    /** The ledger account behind one of the business's payment accounts (accounts.chart_of_account_id). */
    public function cashAccountFor(?int $paymentAccountId): ?int
    {
        if (! $paymentAccountId) {
            return null;
        }
        $id = DB::table('accounts')->where('business_id', Ledger::businessId())->where('id', $paymentAccountId)->value('chart_of_account_id');

        return $id ? (int) $id : null;
    }

    /** The business's payment accounts that have a ledger account (the "Paid into" / "Paid from" lists). */
    public static function paymentAccounts()
    {
        return DB::table('accounts')->where('business_id', Ledger::businessId())->where('is_closed', 0)->whereNull('deleted_at')
            ->whereNotNull('chart_of_account_id')->orderBy('name')->get(['id', 'name']);
    }

    private function firstDetail(string $prefix): ?int
    {
        return ChartOfAccount::postable()->where('account_code', 'like', $prefix.'%')->orderBy('account_code')->value('id');
    }

    public function getAssetRegister(array $filters = [])
    {
        $query = FixedAsset::with('category');
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['location'])) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }
        if (! empty($filters['acquisition_date_from'])) {
            $query->whereDate('acquisition_date', '>=', $filters['acquisition_date_from']);
        }
        if (! empty($filters['acquisition_date_to'])) {
            $query->whereDate('acquisition_date', '<=', $filters['acquisition_date_to']);
        }

        return $query->orderBy('asset_code')->get();
    }

    public function getDepreciationScheduleReport(array $filters = [])
    {
        $query = DepreciationSchedule::with(['fixedAsset.category', 'journalEntry']);
        foreach (['fiscal_year_id' => 'fiscal_year_id', 'accounting_period_id' => 'accounting_period_id', 'status' => 'status', 'asset_id' => 'fixed_asset_id'] as $key => $col) {
            if (! empty($filters[$key])) {
                $query->where($col, $filters[$key]);
            }
        }
        if (! empty($filters['category_id'])) {
            $query->whereHas('fixedAsset', fn ($q) => $q->where('category_id', $filters['category_id']));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('depreciation_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('depreciation_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('depreciation_date', 'desc')->get();
    }
}
