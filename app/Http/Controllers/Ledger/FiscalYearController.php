<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Models\AccountingPeriod;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Services\PeriodControls;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Fiscal years (exercices) and their months: paxhitestbed
 * Admin\FiscalYearController, same screens and rules, on this business's
 * years only (BelongsToBusiness). Closing or reopening one month goes through
 * the review dialog (PeriodControls).
 */
class FiscalYearController extends LedgerController
{
    /**
     * Display a listing of fiscal years
     */
    public function index()
    {
        $this->allow('accounting-period-view');
        $data['fiscalYears'] = FiscalYear::withCount(['accountingPeriods', 'journalEntries'])
            ->orderBy('start_date', 'desc')
            ->get();
        foreach ($data['fiscalYears'] as $year) {
            $year->shape_warning = self::shapeWarning($year);
        }

        return view('ledger.fiscal-years.index', $data);
    }

    /**
     * Show the form for creating a new fiscal year
     */
    public function create()
    {
        $this->allow('accounting-period-create');

        return view('ledger.fiscal-years.create');
    }

    /**
     * Store a newly created fiscal year
     */
    public function store(Request $request)
    {
        $this->allow('accounting-period-create');
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            // the overlap rule hangs off end_date: a closure rule is skipped when its attribute is absent
            'end_date' => ['required', 'date', 'after:start_date', $this->noOverlapRule($request)],
            'is_active' => 'nullable|boolean',
        ], [], $this->attributes());

        try {
            DB::beginTransaction();

            $isActive = (int) $request->input('is_active', 0);
            if ($isActive == 1) {
                FiscalYear::where('is_active', true)->update(['is_active' => false]);
            }

            $fiscalYear = FiscalYear::create([
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $isActive,
                'created_by' => Auth::id(),
            ]);

            // the form's "Generate Monthly Periods" (paxhitestbed read a create_periods field the form never sent)
            if ($request->boolean('generate_periods') || $request->has('create_periods')) {
                $fiscalYear->generatePeriods(Auth::id());
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->err(back(), __('ledger_fy.msg_created_error').': '.$e->getMessage());
        }

        return $this->ok(route('ledger.fiscal-years.index'), $this->withShape(__('ledger_fy.fiscal_year_created_successfully'), $fiscalYear));
    }

    /**
     * Display the specified fiscal year
     */
    public function show($id)
    {
        $this->allow('accounting-period-view');
        $fiscalYear = FiscalYear::with(['accountingPeriods' => fn ($q) => $q->withCount('journalEntries'), 'closedBy'])->findOrFail($id);

        return view('ledger.fiscal-years.show', [
            'fiscalYear' => $fiscalYear,
            'entryCount' => JournalEntry::where('fiscal_year_id', $fiscalYear->id)->count(),
            'postedCount' => JournalEntry::where('fiscal_year_id', $fiscalYear->id)->where('is_posted', true)->count(),
            'shapeWarning' => self::shapeWarning($fiscalYear),
        ]);
    }

    /**
     * Show the form for editing the specified fiscal year
     */
    public function edit($id)
    {
        $this->allow('accounting-period-edit');

        return view('ledger.fiscal-years.edit', ['fiscalYear' => FiscalYear::findOrFail($id)]);
    }

    /**
     * Update the specified fiscal year
     */
    public function update(Request $request, $id)
    {
        $this->allow('accounting-period-edit');
        $fiscalYear = FiscalYear::findOrFail($id);

        if ($fiscalYear->is_closed) {
            return $this->err(route('ledger.fiscal-years.index'), __('ledger_fy.cannot_edit_closed_fiscal_year'));
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => [
                'required', 'date', 'after:start_date',
                $this->noOverlapRule($request, $id),
                // narrowing a year that already holds entries would leave them outside it
                $this->keepsItsEntriesRule($request, $id),
            ],
        ], [], $this->attributes());

        try {
            DB::beginTransaction();

            $isActive = (int) $request->input('is_active', 0);
            if ($isActive == 1) {
                FiscalYear::where('is_active', true)->where('id', '!=', $id)->update(['is_active' => false]);
            }

            $fiscalYear->update([
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $isActive,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->err(back(), __('ledger_fy.msg_update_error').': '.$e->getMessage());
        }

        return $this->ok(route('ledger.fiscal-years.index'), $this->withShape(__('ledger_fy.fiscal_year_updated_successfully'), $fiscalYear->fresh()));
    }

    /**
     * Delete a fiscal year that holds no entries (paxhitestbed's list offers the
     * button; its controller had no destroy method).
     */
    public function destroy($id)
    {
        $this->allow('accounting-period-delete');
        $fiscalYear = FiscalYear::findOrFail($id);
        $to = route('ledger.fiscal-years.index');

        if ($fiscalYear->is_closed) {
            return $this->err($to, __('ledger_fy.cannot_delete_closed_fiscal_year'));
        }
        if (JournalEntry::withTrashed()->where('fiscal_year_id', $fiscalYear->id)->exists()) {
            return $this->err($to, __('ledger_fy.cannot_delete_fiscal_year_with_entries'));
        }

        DB::transaction(function () use ($fiscalYear) {
            AccountingPeriod::where('fiscal_year_id', $fiscalYear->id)->delete();
            $fiscalYear->delete();
        });

        return $this->ok($to, __('ledger_fy.fiscal_year_deleted_successfully'));
    }

    /**
     * Close the fiscal year
     */
    public function close(Request $request, $id)
    {
        $this->allow('accounting-period-close');
        $request->validate(['closing_note' => 'nullable|string']);
        $to = route('ledger.fiscal-years.show', $id);

        try {
            DB::beginTransaction();

            $fiscalYear = FiscalYear::findOrFail($id);
            if ($fiscalYear->is_closed) {
                throw new \RuntimeException(__('ledger_fy.fiscal_year_already_closed'));
            }
            if ($fiscalYear->accountingPeriods()->where('is_closed', false)->count() > 0) {
                throw new \RuntimeException(__('ledger_fy.close_all_periods_first'));
            }
            if ($fiscalYear->journalEntries()->where('is_posted', false)->count() > 0) {
                throw new \RuntimeException(__('ledger_fy.post_all_entries_first'));
            }

            $fiscalYear->update([
                'is_closed' => true,
                'is_active' => false,
                'closed_by' => Auth::id(),
                'closed_at' => now(),
                'closing_note' => $request->closing_note,
            ]);

            DB::commit();

            return $this->ok($to, __('ledger_fy.fiscal_year_closed_successfully'));
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return $this->err($to, $e->getMessage());
        }
    }

    /**
     * Reopen a closed fiscal year
     */
    public function reopen($id)
    {
        $this->allow('accounting-period-reopen');
        $to = route('ledger.fiscal-years.show', $id);
        $fiscalYear = FiscalYear::findOrFail($id);

        if (! $fiscalYear->is_closed) {
            return $this->err($to, __('ledger_fy.fiscal_year_not_closed'));
        }

        $fiscalYear->update([
            'is_closed' => false,
            'closed_by' => null,
            'closed_at' => null,
            'closing_note' => null,
        ]);

        return $this->ok($to, __('ledger_fy.fiscal_year_reopened_successfully'));
    }

    /**
     * Set fiscal year as active
     */
    public function setActive($id)
    {
        $this->allow('accounting-period-edit');
        $to = route('ledger.fiscal-years.index');
        $fiscalYear = FiscalYear::findOrFail($id);

        if ($fiscalYear->is_closed) {
            return $this->err($to, __('ledger_fy.cannot_activate_closed_fiscal_year'));
        }

        DB::transaction(function () use ($fiscalYear) {
            FiscalYear::where('is_active', true)->update(['is_active' => false]);
            $fiscalYear->update(['is_active' => true]);
        });

        return $this->ok($to, __('ledger_fy.fiscal_year_activated_successfully'));
    }

    /**
     * Generate periods for an existing fiscal year
     */
    public function generatePeriods($id)
    {
        $this->allow('accounting-period-create');
        $to = route('ledger.fiscal-years.show', $id);
        $fiscalYear = FiscalYear::findOrFail($id);

        try {
            DB::transaction(fn () => $fiscalYear->generatePeriods(Auth::id()));
        } catch (\RuntimeException $e) {
            return $this->err($to, $e->getMessage());
        }

        return $this->ok($to, __('ledger_fy.periods_generated_successfully'));
    }

    /* ---- One month: close and reopen, through the review dialog ---------- */

    public function previewClosePeriod($id)
    {
        $this->allow('accounting-period-close');
        $period = AccountingPeriod::findOrFail($id);

        return view('ledger.partials.preview', ['p' => app(PeriodControls::class)->previewClose($period, Auth::id())]);
    }

    public function closePeriod(Request $request, $id)
    {
        $this->allow('accounting-period-close');
        $period = AccountingPeriod::findOrFail($id);
        $to = route('ledger.fiscal-years.show', $period->fiscal_year_id);

        if (! $this->reviewed($request)) {
            return $this->err($to, __('ledger.review_first'));
        }

        try {
            app(PeriodControls::class)->closeMonth($period, Auth::id(), $request->input('closing_note'));
        } catch (\RuntimeException $e) {
            return $this->err($to, $e->getMessage());
        }

        return $this->ok($to, __('ledger_fy.month_closed', ['month' => FiscalYear::periodLabel($period)]));
    }

    public function previewReopenPeriod($id)
    {
        $this->allow('accounting-period-reopen');
        $period = AccountingPeriod::findOrFail($id);

        return view('ledger.partials.preview', ['p' => app(PeriodControls::class)->previewReopen($period, Auth::id())]);
    }

    public function reopenPeriod(Request $request, $id)
    {
        $this->allow('accounting-period-reopen');
        $period = AccountingPeriod::findOrFail($id);
        $to = route('ledger.fiscal-years.show', $period->fiscal_year_id);

        if (! $this->reviewed($request)) {
            return $this->err($to, __('ledger.review_first'));
        }

        try {
            // the review dialog posts the reason as "reason" (paxhitestbed: void_reason)
            $reason = (string) ($request->input('reason') ?? $request->input('void_reason'));
            app(PeriodControls::class)->reopenMonth($period, Auth::id(), $reason);
        } catch (\RuntimeException $e) {
            return $this->err($to, $e->getMessage());
        }

        return $this->ok($to, __('ledger_fy.month_reopened', ['month' => FiscalYear::periodLabel($period)]));
    }

    /* ------------------------------------------------------------------ */

    /**
     * FiscalYear::shapeWarning() (Article 7 of the AUDCIF: twelve months ending
     * 31 December; a first exercice may be shorter or longer), in the user's language.
     */
    public static function shapeWarning(FiscalYear $year): ?string
    {
        if (! $year->shapeWarning()) {
            return null;
        }
        $start = Carbon::parse($year->start_date);
        $end = Carbon::parse($year->end_date);
        if ($end->month !== 12 || $end->day !== 31) {
            return __('ledger_fy.shape_end', ['date' => $end->format('d/m/Y')]);
        }
        if ($start->month < 7) {
            return __('ledger_fy.shape_first_half', ['date' => $start->format('d/m'), 'year' => $start->year]);
        }

        return __('ledger_fy.shape_second_half');
    }

    private function withShape(string $msg, FiscalYear $year): string
    {
        $warning = self::shapeWarning($year);

        return $warning ? $msg.'. '.__('ledger_fy.shape_title').': '.$warning : $msg;
    }

    private function attributes(): array
    {
        return [
            'name' => __('ledger_fy.fiscal_year_name'),
            'start_date' => __('ledger_fy.start_date'),
            'end_date' => __('ledger_fy.end_date'),
        ];
    }

    /**
     * No two fiscal years of this business may cover the same day: with an
     * overlap, "which year does this entry belong to" has two answers.
     */
    private function noOverlapRule(Request $request, $ignoreId = null): \Closure
    {
        return function ($attribute, $value, $fail) use ($request, $ignoreId) {
            if (! $request->filled('start_date') || ! $value) {
                return;
            }

            $clash = FiscalYear::when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->whereDate('start_date', '<=', $value)
                ->whereDate('end_date', '>=', $request->input('start_date'))
                ->first();

            if ($clash) {
                $fail(__('ledger_fy.overlap', [
                    'name' => $clash->name,
                    'from' => $clash->start_date->format('d/m/Y'),
                    'to' => $clash->end_date->format('d/m/Y'),
                ]));
            }
        };
    }

    /**
     * A fiscal year may not be narrowed away from entries it already holds:
     * they would keep its id while falling outside its dates.
     */
    private function keepsItsEntriesRule(Request $request, $id): \Closure
    {
        return function ($attribute, $value, $fail) use ($request, $id) {
            if (! $request->filled('start_date') || ! $value) {
                return;
            }

            $orphaned = JournalEntry::where('fiscal_year_id', $id)
                ->where(function ($query) use ($request, $value) {
                    $query->whereDate('entry_date', '<', $request->input('start_date'))
                        ->orWhereDate('entry_date', '>', $value);
                })
                ->count();

            if ($orphaned > 0) {
                $range = JournalEntry::where('fiscal_year_id', $id)
                    ->selectRaw('MIN(entry_date) as first_entry, MAX(entry_date) as last_entry')
                    ->first();

                $fail(trans_choice('ledger_fy.orphaned', $orphaned, [
                    'count' => $orphaned,
                    'first' => Carbon::parse($range->first_entry)->format('d/m/Y'),
                    'last' => Carbon::parse($range->last_entry)->format('d/m/Y'),
                ]));
            }
        };
    }
}
