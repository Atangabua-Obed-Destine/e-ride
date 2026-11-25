<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\RecurringJournalEntry;
use App\Ledger\Services\RecurringEntryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Recurring entries (paxhitestbed Admin\RecurringEntryController, its
 * corrected working tree). Processing one entry, or all those due, goes
 * through the review dialog (route middleware ledger.review). Every screen
 * answers an ordinary form with a screen and a message: paxhitestbed still
 * answered pause / resume / skip with JSON, which the user saw raw.
 */
class RecurringEntryController extends LedgerController
{
    const FREQUENCIES = ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'semiannually', 'annually'];

    protected $service;

    public function __construct(RecurringEntryService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->allow('recurring-entry-list');
        $query = RecurringJournalEntry::with(['lines', 'creator']);
        $today = now()->toDateString();

        if ($request->filled('frequency')) {
            $query->where('frequency', $request->frequency === 'yearly' ? 'annually' : $request->frequency);
        }
        // active, paused or expired (as getStatusAttribute reads them)
        $expired = function ($q) use ($today) {
            // NULL-safe both ways, so whereNot() keeps the open-ended ones
            $q->where(fn ($q) => $q->whereNotNull('end_date')->whereDate('end_date', '<', $today))
                ->orWhere(fn ($q) => $q->whereNotNull('occurrences')->whereRaw('occurrences_completed >= occurrences'));
        };
        if ($request->status === 'expired') {
            $query->where($expired);
        } elseif (in_array($request->status, ['active', 'paused'], true)) {
            $query->where('is_active', $request->status === 'active')->whereNot($expired);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"));
        }

        $summary = $this->service->getSummary();

        return view('ledger.recurring-entries.index', [
            'entries' => $query->orderBy('next_run_date')->paginate(20)->appends($request->query()),
            'summary' => $summary,
            'upcoming' => $this->service->getUpcoming(7),
            'statistics' => $summary + ['monthly_total' => $summary['total_monthly_amount'] ?? 0],
        ]);
    }

    public function create()
    {
        $this->allow('recurring-entry-create');

        return view('ledger.recurring-entries.create', ['accounts' => $this->accounts(), 'frequencies' => RecurringJournalEntry::getFrequencies()]);
    }

    public function store(Request $request)
    {
        $this->allow('recurring-entry-create');
        $this->normalise($request);
        $request->validate($this->rules(true), [], $this->attributes());

        try {
            $entry = $this->service->createTemplate($request->all());
        } catch (\RuntimeException $e) {
            return $this->err(back(), __('ledger_periodend.error_creating_entry').': '.$e->getMessage());
        }

        return $this->ok(route('ledger.recurring-entries.show', $entry->id), __('ledger_periodend.recurring_entry_created_successfully'));
    }

    public function show($id)
    {
        $this->allow('recurring-entry-list');
        $recurringEntry = RecurringJournalEntry::with(['lines.account', 'creator',
            'journalEntries' => fn ($q) => $q->with('lines')->orderBy('entry_date', 'desc')->limit(10)])->findOrFail($id);

        return view('ledger.recurring-entries.show', ['recurringEntry' => $recurringEntry]);
    }

    public function edit($id)
    {
        $this->allow('recurring-entry-edit');

        return view('ledger.recurring-entries.edit', [
            'recurringEntry' => RecurringJournalEntry::with('lines.account')->findOrFail($id),
            'accounts' => $this->accounts(),
            'frequencies' => RecurringJournalEntry::getFrequencies(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->allow('recurring-entry-edit');
        $recurringEntry = RecurringJournalEntry::findOrFail($id);
        $this->normalise($request);
        $request->validate($this->rules(false), [], $this->attributes());

        try {
            $entry = $this->service->updateTemplate($recurringEntry, $request->all());
        } catch (\RuntimeException $e) {
            return $this->err(back(), __('ledger_periodend.error_updating_entry').': '.$e->getMessage());
        }

        return $this->ok(route('ledger.recurring-entries.show', $entry->id), __('ledger_periodend.recurring_entry_updated_successfully'));
    }

    public function destroy($id)
    {
        $this->allow('recurring-entry-delete');
        $recurringEntry = RecurringJournalEntry::findOrFail($id);
        $recurringEntry->lines()->delete();
        $recurringEntry->delete();

        return $this->ok(route('ledger.recurring-entries.index'), __('ledger_periodend.recurring_entry_deleted_successfully'));
    }

    public function pause($id)
    {
        $this->allow('recurring-entry-edit');
        $this->service->pause(RecurringJournalEntry::findOrFail($id));

        return $this->ok(back(), __('ledger_periodend.recurring_entry_paused'));
    }

    public function resume(Request $request, $id)
    {
        $this->allow('recurring-entry-edit');
        $request->validate(['next_run_date' => 'nullable|date']);
        $this->service->resume(RecurringJournalEntry::findOrFail($id), $request->next_run_date);

        return $this->ok(back(), __('ledger_periodend.recurring_entry_resumed'));
    }

    public function skipNext($id)
    {
        $this->allow('recurring-entry-edit');
        $entry = $this->service->skipNext(RecurringJournalEntry::findOrFail($id));

        return $this->ok(back(), __('ledger_periodend.next_occurrence_skipped', ['date' => $entry->next_run_date->format('d/m/Y')]));
    }

    public function process(Request $request, $id)
    {
        $this->allow('recurring-entry-process');
        $recurringEntry = RecurringJournalEntry::with('lines')->findOrFail($id);
        $back = route('ledger.recurring-entries.show', $recurringEntry->id);
        $request->validate(['date' => 'nullable|date']);

        try {
            $journalEntry = $this->service->processEntry($recurringEntry, $request->date, Auth::id());
        } catch (\RuntimeException $e) {
            return $this->err($back, $e->getMessage());
        }
        if (! $journalEntry) {
            return $this->err($back, __('ledger_periodend.entry_not_due'));
        }

        return $this->ok(route('ledger.journal-entries.show', $journalEntry->id), __('ledger_periodend.entry_written', [
            'number' => $journalEntry->entry_number, 'date' => $journalEntry->entry_date->format('d/m/Y'),
        ]).' '.($journalEntry->is_posted ? __('ledger_periodend.it_is_posted') : __('ledger_periodend.check_and_post')));
    }

    public function processAll(Request $request)
    {
        $this->allow('recurring-entry-process');
        $request->validate(['date' => 'nullable|date']);
        $results = $this->service->processDueEntries($request->date, Auth::id());
        $to = route('ledger.recurring-entries.index');

        $msg = trans_choice('ledger_periodend.entries_written', $results['created'], ['count' => $results['created']]);
        if ($results['errors']) {
            $msg .= ' '.implode(' ', array_map(fn ($e) => $e['name'].': '.$e['error'], $results['errors']));

            return $results['created'] ? $this->ok($to, $msg) : $this->err($to, $msg);
        }

        return $this->ok($to, $msg);
    }

    public function duplicate($id)
    {
        $this->allow('recurring-entry-create');
        try {
            $newEntry = $this->service->duplicate(RecurringJournalEntry::with('lines')->findOrFail($id));
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.recurring-entries.edit', $newEntry->id), __('ledger_periodend.recurring_entry_duplicated'));
    }

    /** The next runs (AJAX), as paxhitestbed. */
    public function upcoming(Request $request)
    {
        $this->allow('recurring-entry-list');

        return response()->json(['success' => true, 'data' => $this->service->getUpcoming((int) $request->input('days', 30))]);
    }

    /* ------------------------------------------------------------------ */

    private function accounts()
    {
        return ChartOfAccount::postable()->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_name_fr']);
    }

    /** The form names the fields template_name and total_occurrences (paxhitestbed), and offers "yearly". */
    private function normalise(Request $request): void
    {
        $request->merge(array_filter([
            'name' => $request->input('name', $request->input('template_name')),
            'occurrences' => $request->input('occurrences', $request->input('total_occurrences')),
        ], fn ($v) => $v !== null && $v !== ''));
        if ($request->input('frequency') === 'yearly') {
            $request->merge(['frequency' => 'annually']);
        }
        if (! $request->has('occurrences') && $request->has('total_occurrences')) {
            $request->merge(['occurrences' => null]);
        }
    }

    private function rules(bool $creating): array
    {
        $bid = \App\Ledger\Ledger::businessId();
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'journal_type' => ['nullable', Rule::in(JournalEntryController::TYPES)],
            'frequency' => ['required', Rule::in(self::FREQUENCIES)],
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'start_date' => ($creating ? 'required' : 'nullable').'|date',
            'end_date' => 'nullable|date|after:start_date',
            'occurrences' => 'nullable|integer|min:1',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)->where('account_category', 'detail')],
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:500',
        ];
        if (! $creating) {
            $rules['end_date'] = 'nullable|date';
        }

        return $rules;
    }

    private function attributes(): array
    {
        return [
            'name' => __('ledger_periodend.template_name'),
            'frequency' => __('ledger_periodend.frequency'),
            'start_date' => __('ledger_periodend.start_date'),
            'end_date' => __('ledger_periodend.end_date'),
            'occurrences' => __('ledger_periodend.total_occurrences'),
            'day_of_month' => __('ledger_periodend.day_of_month'),
            'lines' => __('ledger_periodend.entry_lines'),
            'lines.*.account_id' => __('ledger_periodend.account'),
            'lines.*.debit' => __('ledger_periodend.debit'),
            'lines.*.credit' => __('ledger_periodend.credit'),
        ];
    }
}
