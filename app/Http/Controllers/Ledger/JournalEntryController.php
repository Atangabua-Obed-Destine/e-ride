<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Services\LedgerPoster;
use App\Ledger\Services\Preview;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Journal entries: paxhitestbed's Admin\JournalEntryController, on PosInnova.
 *
 * Same screens, fields, buttons and rules. Differences:
 *  - every write goes through LedgerPoster (drafts, lines, reversals) and
 *    JournalEntry::post, never straight into journal_entry_lines;
 *  - posting (from the list, the entry, or "Save & Post") goes through the
 *    review dialog, as reversing already does in paxhitestbed;
 *  - an entry written by the system (sales, purchases, payments, expenses,
 *    automatic reversals) cannot be edited, deleted, un-posted or reversed
 *    here; the page says why.
 */
class JournalEntryController extends LedgerController
{
    const TYPES = ['general', 'sales', 'purchase', 'cash', 'bank', 'adjustment'];

    const ALL_TYPES = ['general', 'sales', 'purchase', 'cash', 'bank', 'adjustment', 'opening', 'closing'];

    /* ---- Lists ----------------------------------------------------------- */

    public function index(Request $request)
    {
        $this->allow('journal-entry-view');

        $query = JournalEntry::with(['fiscalYear', 'accountingPeriod', 'reversingEntry']);

        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year_id', $request->fiscal_year);
        }
        if ($request->filled('journal_type')) {
            $query->where('journal_type', $request->journal_type);
        }
        if ($request->filled('is_posted')) {
            $query->where('is_posted', (bool) $request->is_posted);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('entry_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('entry_date', '<=', $request->end_date);
        }

        return view('ledger.journal-entries.index', [
            'entries' => $query->orderBy('entry_date', 'desc')->orderBy('entry_number', 'desc')->paginate(25),
            'fiscalYears' => FiscalYear::orderBy('start_date', 'desc')->get(),
        ]);
    }

    public function trash()
    {
        $this->allow('journal-entry-delete');

        return view('ledger.journal-entries.trash', [
            'entries' => JournalEntry::onlyTrashed()->with(['fiscalYear', 'accountingPeriod', 'creator'])
                ->orderBy('deleted_at', 'desc')->paginate(25),
        ]);
    }

    /* ---- Create, show, edit ---------------------------------------------- */

    public function create()
    {
        $this->allow('journal-entry-create');

        return view('ledger.journal-entries.create', [
            'fiscalYears' => FiscalYear::where('is_closed', false)->orderBy('start_date', 'desc')->get(),
            'activeFiscalYear' => FiscalYear::where('is_active', true)->first(),
            'accounts' => $this->postableAccounts(),
        ]);
    }

    public function store(Request $request)
    {
        $this->allow('journal-entry-create');
        $posting = $request->filled('post_immediately');
        if ($posting) {
            $this->allow('journal-entry-post');
            if (! $this->reviewed($request)) {
                return $this->needReview();
            }
        }

        $request->validate($this->rules(true));

        try {
            $entry = DB::transaction(function () use ($request, $posting) {
                $entry = $this->saveDraft($request);
                if ($posting) {
                    $entry->post(auth()->id());
                }

                return $entry;
            });
        } catch (\Exception $e) {
            return $this->err(back(), __('ledger_je.msg_created_error').': '.$e->getMessage());
        }

        return $this->ok(route('ledger.journal-entries.show', $entry->id), $posting
            ? __('ledger_je.journal_entry_posted_successfully') : __('ledger_je.journal_entry_created_successfully'));
    }

    public function show($id)
    {
        $this->allow('journal-entry-view');
        $entry = JournalEntry::with(['lines.account', 'fiscalYear', 'accountingPeriod', 'postedBy', 'reversingEntry', 'originalEntry'])->findOrFail($id);

        return view('ledger.journal-entries.show', [
            'entry' => $entry,
            'lockReason' => self::lockReason($entry),
        ]);
    }

    public function edit($id)
    {
        $this->allow('journal-entry-edit');
        $entry = JournalEntry::with('lines.account')->findOrFail($id);
        if (! $entry->is_posted && ($why = self::lockReason($entry))) {
            return $this->err(route('ledger.journal-entries.show', $id), $why);
        }

        return view('ledger.journal-entries.edit', [
            'entry' => $entry,
            'accounts' => $this->postableAccounts(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->allow('journal-entry-edit');
        $entry = JournalEntry::findOrFail($id);

        if ($entry->is_posted) {
            return $this->err(route('ledger.journal-entries.show', $id), __('ledger_je.cannot_edit_posted_entry'));
        }
        if ($why = self::lockReason($entry)) {
            return $this->err(route('ledger.journal-entries.show', $id), $why);
        }

        $request->validate($this->rules(false));

        try {
            DB::transaction(fn () => $this->saveDraft($request, $entry));
        } catch (\Exception $e) {
            return $this->err(back(), __('ledger_je.msg_update_error').': '.$e->getMessage());
        }

        return $this->ok(route('ledger.journal-entries.show', $entry->id), __('ledger_je.journal_entry_updated_successfully'));
    }

    /* ---- Delete, trash ---------------------------------------------------- */

    public function destroy($id)
    {
        $this->allow('journal-entry-delete');
        $to = route('ledger.journal-entries.index');
        try {
            $entry = JournalEntry::findOrFail($id);
            if ($entry->is_posted) {
                return $this->err($to, __('ledger_je.cannot_delete_posted_entry'));
            }
            if ($entry->is_system_generated) {
                return $this->err($to, __('ledger_je.cannot_delete_system_entry'));
            }
            $entry->delete();
        } catch (\Exception $e) {
            return $this->err($to, __('ledger_je.msg_delete_error').': '.$e->getMessage());
        }

        return $this->ok($to, __('ledger_je.journal_entry_deleted_successfully'));
    }

    public function restore($id)
    {
        $this->allow('journal-entry-delete');
        try {
            JournalEntry::onlyTrashed()->findOrFail($id)->restore();
        } catch (\Exception $e) {
            return $this->err(back(), __('ledger_je.msg_restore_error').': '.$e->getMessage());
        }

        return $this->ok(route('ledger.journal-entries.trash'), __('ledger_je.journal_entry_restored_successfully'));
    }

    public function forceDelete($id)
    {
        $this->allow('journal-entry-delete');
        try {
            $entry = JournalEntry::onlyTrashed()->findOrFail($id);
            if ($entry->is_posted) {
                return $this->err(back(), __('ledger_je.cannot_permanently_delete_posted_entry'));
            }
            // the lines go with it (journal_entry_lines.journal_entry_id cascades)
            $entry->forceDelete();
        } catch (\Exception $e) {
            return $this->err(back(), __('ledger_je.msg_delete_error').': '.$e->getMessage());
        }

        return $this->ok(route('ledger.journal-entries.trash'), __('ledger_je.journal_entry_permanently_deleted'));
    }

    /* ---- Posting, through the review dialog ------------------------------- */

    /**
     * What posting would do. {id} is a draft's id, or "new" for the create
     * form's "Save & Post" (the form's fields are posted here).
     */
    public function previewPost(Request $request, $id)
    {
        $p = Preview::blank();
        $uid = auth()->id();

        if ($id === 'new') {
            $this->allow('journal-entry-create');
            $this->allow('journal-entry-post');
            $v = Validator::make($request->all(), $this->rules(true));
            $date = $request->input('entry_date');
            $p['summary'] = [
                [__('ledger_je.p_entry'), __('ledger_je.p_new_entry').($this->validDate($date) ? ' · '.Carbon::parse($date)->format('d/m/Y') : '')],
                [__('ledger_je.p_description'), (string) $request->input('description')],
                [__('ledger_je.p_amount'), Ledger::money(collect($request->input('lines', []))->sum(fn ($l) => (float) ($l['debit'] ?? 0)))],
            ];
            if ($v->fails()) {
                $p['ok'] = false;
                $p['problems'] = $v->errors()->all();
            } else {
                Preview::dryRun($p, function () use ($request, $uid) {
                    $this->saveDraft($request)->post($uid);
                });
            }
        } else {
            $this->allow('journal-entry-post');
            $entry = JournalEntry::with('lines')->findOrFail($id);
            $p['summary'] = [
                [__('ledger_je.p_entry'), $entry->entry_number.' · '.$entry->entry_date->format('d/m/Y')],
                [__('ledger_je.p_description'), $entry->description],
                [__('ledger_je.p_amount'), Ledger::money($entry->lines->sum('debit'))],
            ];
            Preview::dryRun($p, function () use ($entry, $uid, &$p) {
                $e = $this->postDraft($entry->id, $uid);
                $p['entries'][] = Preview::entry($e->fresh(['lines.account']));
            });
        }

        if ($p['ok'] && ! empty($p['entries'][0]['period'])) {
            $p['summary'][] = [__('ledger_je.p_period'), $p['entries'][0]['period']];
        }
        $p['notes'][] = __('ledger_je.p_post_note');

        return view('ledger.partials.preview', ['p' => $p]);
    }

    public function post(Request $request, $id)
    {
        $this->allow('journal-entry-post');
        $show = route('ledger.journal-entries.show', $id);
        if (! $this->reviewed($request)) {
            return $this->err($show, __('ledger.review_first'));
        }

        try {
            $this->postDraft($id, auth()->id());
        } catch (\Exception $e) {
            return $this->err($show, $e->getMessage());
        }

        return $this->ok($show, __('ledger_je.journal_entry_posted_successfully'));
    }

    public function unpost($id)
    {
        $this->allow('journal-entry-unpost');
        $show = route('ledger.journal-entries.show', $id);

        try {
            DB::transaction(function () use ($id) {
                $entry = JournalEntry::with(['lines.account', 'accountingPeriod', 'fiscalYear', 'reversingEntry'])
                    ->whereKey($id)->lockForUpdate()->firstOrFail();

                if (! $entry->is_posted) {
                    throw new \RuntimeException(__('ledger_je.entry_not_posted'));
                }
                if ($why = self::lockReason($entry)) {
                    throw new \RuntimeException($why);
                }
                if ($entry->is_reversed) {
                    throw new \RuntimeException(__('ledger_je.cannot_unpost_reversed', ['number' => optional($entry->reversingEntry)->entry_number ?? '-']));
                }
                if ($entry->accountingPeriod && $entry->accountingPeriod->is_closed) {
                    throw new \RuntimeException(__('ledger_je.cannot_unpost_entry_in_closed_period'));
                }
                if ($entry->fiscalYear && $entry->fiscalYear->is_closed) {
                    throw new \RuntimeException(__('ledger_je.cannot_unpost_in_closed_year'));
                }

                $entry->unpost();
            });
        } catch (\Exception $e) {
            return $this->err($show, $e->getMessage());
        }

        return $this->ok($show, __('ledger_je.journal_entry_unposted_successfully'));
    }

    public function duplicate($id)
    {
        $this->allow('journal-entry-create');

        try {
            $original = JournalEntry::with('lines')->findOrFail($id);
            $lines = $original->lines->map(fn ($l) => [
                'account_id' => $l->account_id,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
                'description' => $l->description,
                'contact_id' => $l->contact_id,
            ])->all();

            // a copy is dated today, so it belongs to today's fiscal year and month
            $copy = LedgerPoster::draft([
                'entry_date' => now()->toDateString(),
                'journal_type' => $original->journal_type,
                'description' => $original->description.' '.__('ledger_je.copy_suffix'),
                'reference_number' => $original->reference_number,
                'is_system_generated' => false,
            ], $lines, auth()->id());
        } catch (\Exception $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.journal-entries.edit', $copy->id), __('ledger_je.journal_entry_duplicated_successfully'));
    }

    /* ---- Reversing a posted manual entry, through the review dialog ------- */

    public function previewReverse(Request $request, $id)
    {
        $this->allow('journal-entry-reverse');
        $entry = JournalEntry::with('lines')->findOrFail($id);
        $date = $request->input('reversal_date');
        $date = $this->validDate($date) ? Carbon::parse($date)->toDateString() : now()->toDateString();

        $p = Preview::blank();
        $p['summary'] = [
            [__('ledger_je.p_entry'), $entry->entry_number.' · '.$entry->entry_date->format('d/m/Y')],
            [__('ledger_je.p_description'), $entry->description],
            [__('ledger_je.p_amount'), Ledger::money($entry->total_debit)],
            [__('ledger_je.p_reversal_dated'), Carbon::parse($date)->format('d/m/Y')],
        ];
        $uid = auth()->id();
        Preview::dryRun($p, fn () => $this->reverseEntry($entry->id, $date, $uid));
        if (isset($p['entries'][0])) {
            $p['entries'][0]['title'] = __('ledger_je.p_reversing_entry');
        }
        $p['notes'][] = __('ledger_je.p_reverse_note');

        return view('ledger.partials.preview', ['p' => $p]);
    }

    public function reverse(Request $request, $id)
    {
        $this->allow('journal-entry-reverse');
        $show = route('ledger.journal-entries.show', $id);
        if (! $this->reviewed($request)) {
            return $this->err($show, __('ledger.review_first'));
        }

        $request->validate(['reversal_date' => 'required|date', 'reason' => 'nullable|string|max:500']);
        $reason = $request->input('reason') ?: $request->input('void_reason');

        try {
            $reversal = $this->reverseEntry($id, $request->input('reversal_date'), auth()->id(), $reason);
        } catch (\RuntimeException $e) {
            return $this->err($show, $e->getMessage());
        }

        return $this->ok(route('ledger.journal-entries.show', $reversal->id), __('ledger_je.reversed_by_msg', ['number' => $reversal->entry_number]));
    }

    /* ---- Helpers ---------------------------------------------------------- */

    /**
     * Why an entry cannot be changed by hand (edited, deleted, un-posted,
     * reversed), or null when it can.
     */
    public static function lockReason(JournalEntry $entry): ?string
    {
        if ($entry->reversed_entry_id) {
            $original = JournalEntry::withTrashed()->find($entry->reversed_entry_id);

            return __('ledger_je.reversal_locked', ['number' => $original->entry_number ?? '#'.$entry->reversed_entry_id]);
        }
        if ($entry->is_system_generated) {
            return __('ledger_je.from_other_screen', ['source' => self::sourceLabel($entry)]);
        }

        return null;
    }

    /** Which screen wrote a system entry, in words. */
    public static function sourceLabel(JournalEntry $entry): string
    {
        $type = $entry->reference_type;
        if ($type === 'transaction' && $entry->reference_id) {
            $type = DB::table('transactions')->where('id', $entry->reference_id)->value('type') ?: $type;
        } elseif ($type === 'opening_balances') {
            $type = 'opening_balance';
        } elseif (in_array($type, ['journal_entry_reversal', 'transaction_mapping'])) {
            return __('ledger_je.reversal_source');
        }
        if (! $type) {
            return __('ledger_je.a_module');
        }

        return Lang::has('ledger.doc_'.$type) ? __('ledger.doc_'.$type) : $type;
    }

    public static function typeLabel(?string $type): string
    {
        return Lang::has('ledger_je.type_'.$type) ? __('ledger_je.type_'.$type) : ucfirst((string) $type);
    }

    /** Detail, active accounts of this business, by code (the account pickers). */
    private function postableAccounts()
    {
        return ChartOfAccount::postable()->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name', 'account_name_fr', 'normal_balance']);
    }

    private function rules(bool $withYear): array
    {
        $bid = Ledger::businessId();
        $rules = [
            'entry_date' => 'required|date',
            'journal_type' => ['required', Rule::in(self::ALL_TYPES)],
            'description' => 'required|string',
            'reference_number' => 'nullable|string|max:255',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid),
                function ($attribute, $value, $fail) {
                    if (! ChartOfAccount::postable()->whereKey($value)->exists()) {
                        $fail(__('ledger_je.choose_detail_account'));
                    }
                }],
            'lines.*.description' => 'nullable|string',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
        ];
        if ($withYear) {
            // The chosen year must contain the entry's date (two separate fields that can disagree).
            $rules['fiscal_year_id'] = ['required', Rule::exists('fiscal_years', 'id')->where('business_id', $bid),
                function ($attribute, $value, $fail) {
                    $year = FiscalYear::find($value);
                    $date = request()->input('entry_date');
                    if (! $year || ! $this->validDate($date)) {
                        return;
                    }
                    $date = Carbon::parse($date);
                    if ($date->lt($year->start_date) || $date->gt($year->end_date)) {
                        $fail(__('ledger_je.date_outside_year', [
                            'date' => $date->format('d/m/Y'), 'year' => $year->name,
                            'start' => Carbon::parse($year->start_date)->format('d/m/Y'), 'end' => Carbon::parse($year->end_date)->format('d/m/Y'),
                        ]));
                    }
                }];
        }

        return $rules;
    }

    /**
     * paxhitestbed's checks on the typed lines: the entry balances, and no
     * line is both a debit and a credit. Lines with no amount are dropped.
     */
    private function linesFrom(Request $request): array
    {
        $typed = array_values($request->input('lines', []));
        $dr = round(collect($typed)->sum(fn ($l) => (float) ($l['debit'] ?? 0)), 2);
        $cr = round(collect($typed)->sum(fn ($l) => (float) ($l['credit'] ?? 0)), 2);
        if (abs($dr - $cr) > 0.01) {
            throw new \RuntimeException(__('ledger_je.journal_entry_not_balanced'));
        }

        $lines = [];
        foreach ($typed as $i => $l) {
            $d = (float) ($l['debit'] ?? 0);
            $c = (float) ($l['credit'] ?? 0);
            if ($d == 0 && $c == 0) {
                continue;
            }
            if ($d > 0 && $c > 0) {
                $account = ChartOfAccount::find($l['account_id'] ?? 0);
                throw new \RuntimeException(__('ledger_je.line_both_sides', ['n' => $i + 1, 'account' => $account ? Preview::accountName($account) : '']));
            }
            $lines[] = [
                'account_id' => $l['account_id'],
                'debit' => $d,
                'credit' => $c,
                'description' => ($l['description'] ?? null) ?: $request->input('description'),
            ];
        }

        return $lines;
    }

    /** Write a new draft, or change an existing one, from the form. */
    private function saveDraft(Request $request, ?JournalEntry $entry = null): JournalEntry
    {
        $lines = $this->linesFrom($request);
        $header = [
            'entry_date' => Carbon::parse($request->input('entry_date'))->toDateString(),
            'journal_type' => $request->input('journal_type'),
            'description' => $request->input('description'),
            'reference_number' => $request->input('reference_number'),
        ];

        if (! $entry) {
            return LedgerPoster::draft($header + ['is_system_generated' => false], $lines, auth()->id());
        }

        $entry->fill($header + [
            'fiscal_year_id' => optional(FiscalYear::forDate($header['entry_date']))->id,
            'accounting_period_id' => optional(FiscalYear::periodForDate($header['entry_date']))->id,
            'updated_by' => auth()->id(),
        ])->save();
        LedgerPoster::replaceLines($entry, $lines);

        return $entry->fresh(['lines']);
    }

    /** Post a draft (the checks are JournalEntry::post's, i.e. LedgerPoster::assertPostable). */
    private function postDraft($id, ?string $uid): JournalEntry
    {
        return DB::transaction(function () use ($id, $uid) {
            $entry = JournalEntry::with('lines.account')->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($entry->is_posted) {
                throw new \RuntimeException(__('ledger_je.entry_already_posted'));
            }
            $entry->post($uid);

            return $entry;
        });
    }

    /**
     * paxhitestbed PeriodControls::reverseEntry: only a posted entry, not
     * already reversed, not itself a reversal, not written by the system;
     * the date not before the original (LedgerPoster::reverse checks that).
     */
    private function reverseEntry($id, string $date, ?string $uid, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($id, $date, $uid, $reason) {
            $entry = JournalEntry::with('lines')->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! $entry->is_posted) {
                throw new \RuntimeException(__('ledger_je.only_posted_reversed'));
            }
            if ($entry->is_reversed) {
                throw new \RuntimeException(__('ledger_je.already_reversed'));
            }
            if ($entry->reversed_entry_id) {
                throw new \RuntimeException(__('ledger_je.is_reversal'));
            }
            if ($entry->is_system_generated) {
                throw new \RuntimeException(__('ledger_je.from_other_screen', ['source' => self::sourceLabel($entry)]));
            }

            return LedgerPoster::reverse($entry, 'journal_entry_reversal', $entry->id, $uid, $reason, $date);
        });
    }

    private function validDate($d): bool
    {
        if (! is_string($d) || $d === '') {
            return false;
        }
        try {
            Carbon::parse($d);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
