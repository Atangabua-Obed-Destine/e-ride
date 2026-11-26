<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Models\TransactionMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusMoneyMove;
use Modules\BusManagement\Entities\BusStatement;

/**
 * Keeps the ledger in step with the bus records. E-Ride's version of
 * posinnova's LedgerSync (which does it for POS documents); the mechanism is
 * posinnova's, copied as it is (apply, fail, postingDate, ensureYear):
 *
 * For a record it asks PostingRules what entry it should have now, compares
 * that with what it posted last time (a fingerprint of the lines), and when
 * they differ reverses the old entry and posts the new one, both in one
 * database transaction. paxhitestbed's TransactionAutoMapService::remap.
 *
 * The bus records: each money move (bus_money_moves, append-only: a ticket
 * paid, a refund, a move to another trip), each trip that left (its held
 * ticket money becomes sales), and E-Ride's monthly statement (owed, then
 * paid). A trip's moves follow it: when a trip changes (it leaves, or runs
 * again after being counted as gone), its moves are looked at again too.
 *
 * Selling a ticket never fails because of the books: if posting is refused (no
 * fiscal year, a mapping missing) the mapping is marked failed with the
 * reason, Ledger health lists it, and the sync tries again (every few minutes
 * from the scheduler, and whenever the record changes).
 *
 * E-Ride difference: bus records have UUIDs, so an entry's reference is its
 * mapping row (reference_type = the record kind, reference_id = the mapping's id).
 */
class LedgerSync
{
    private static $queue = [];

    private static $registered = false;

    /** Ask for a record to be synced at the end of the request (after its database work is committed). */
    public static function queue(string $kind, int|string $id, int $businessId): void
    {
        if (! Ledger::enabledFor($businessId)) {
            return;
        }
        self::$queue[$businessId][$kind.':'.$id] = [$kind, $id];
        if (! self::$registered) {
            self::$registered = true;
            app()->terminating(function () {
                self::flush();
            });
        }
    }

    /** Sync everything queued. Tests and commands call it directly. */
    public static function flush(): array
    {
        $done = [];
        \App\Ledger\ERide\BusBook::forget(); // payment accounts may have been added since the last run
        while (! empty(self::$queue)) {
            $queue = self::$queue;
            self::$queue = [];
            foreach ($queue as $businessId => $items) {
                if (! Ledger::isSetUp($businessId)) {
                    continue;
                }
                Ledger::forBusiness($businessId, function () use ($items, &$done) {
                    // E-Ride: the payment account books touched are rebuilt once, at the end (App\Ledger\ERide\BusBook)
                    PaymentBook::deferRecompute(function () use ($items, &$done) {
                        $sync = new self();
                        foreach ($items as [$kind, $id]) {
                            try {
                                $done[$kind.':'.$id] = $sync->record($kind, $id);
                            } catch (\Throwable $e) {
                                \Log::error('Ledger sync '.$kind.' '.$id.': '.$e->getMessage());
                                $done[$kind.':'.$id] = 'error';
                            }
                        }
                    });
                });
            }
        }

        return $done;
    }

    // ------------------------------------------------------------------

    private $rules;

    private $postFrom;

    public function __construct()
    {
        $this->rules = new PostingRules();
        $this->postFrom = AccountingSetting::getValue('post_from_date');
    }

    /** @return string same|posted|reposted|reversed|none|failed|history */
    public function record(string $kind, int|string $id): string
    {
        return match ($kind) {
            PostingRules::MOVE => $this->move((int) $id),
            PostingRules::DEPARTURE => $this->departure((string) $id),
            PostingRules::STATEMENT, PostingRules::STATEMENT_PAID => $this->statement((string) $id, $kind),
            PostingRules::INCOME, PostingRules::EXPENSE, PostingRules::SUPPLIER_BILL, PostingRules::SUPPLIER_PAYMENT,
            PostingRules::PAYMENT_TRANSFER, PostingRules::PAYMENT_ACCOUNT_TXN,
            PostingRules::PAYROLL, PostingRules::PAYROLL_PAYMENT, PostingRules::STAFF_LOAN, PostingRules::TAX_REMITTANCE => $this->accountsRecord($kind, (int) $id),
            \App\Ledger\ERide\HeadOffice::MOVE, \App\Ledger\ERide\HeadOffice::STATEMENT_PAID => \App\Ledger\ERide\HeadOffice::record($this, $kind, $id),
            default => 'none',
        };
    }

    /** posinnova's apply(), for the E-Ride rules kept outside this file (App\Ledger\ERide\HeadOffice). */
    public function applyFor(TransactionMapping $map, ?callable $expected, string $kind, int|string $id): string
    {
        return $this->apply($map, $expected, $kind, $id);
    }

    /** The bus branch whose books are open. */
    private function agencyId(): ?string
    {
        $row = DB::table('ledger_businesses')->where('id', Ledger::businessId())->first();

        return $row && $row->owner_type === \App\Ledger\ERide\Books::AGENCY ? (string) $row->owner_id : null;
    }

    public function move(int $id): string
    {
        $m = BusMoneyMove::find($id);
        if ($m && (string) $m->agency_id !== $this->agencyId()) {
            return 'none';
        }
        $map = TransactionMapping::where('transaction_type', PostingRules::MOVE)->where('transaction_id', (string) $id)->first();
        if (! $m) {
            return $map ? $this->apply($map, null, PostingRules::MOVE, $id) : 'none';
        }
        if (! $map && $this->isHistory($m->at)) {
            return 'history';
        }
        $map = $map ?: new TransactionMapping(['transaction_type' => PostingRules::MOVE, 'transaction_id' => (string) $id]);

        return $this->apply($map, fn () => $this->rules->forMove($m), PostingRules::MOVE, $id);
    }

    public function departure(string $id): string
    {
        $d = BusDeparture::with('destination')->find($id);
        if ($d && (string) $d->agency_id !== $this->agencyId()) {
            return 'none';
        }
        $map = TransactionMapping::where('transaction_type', PostingRules::DEPARTURE)->where('transaction_id', $id)->first();
        if (! $d) {
            return $map ? $this->apply($map, null, PostingRules::DEPARTURE, $id) : 'none';
        }
        $left = $this->rules->leftAt($d);
        if (! $map && $left && $this->isHistory($left->toDateString())) {
            return 'history';
        }
        // its moves first: one made after the trip left posts to sales, before it to the held tickets
        foreach (BusMoneyMove::where('agency_id', $d->agency_id)->where(fn ($q) => $q->where('departure_id', $id)->orWhere('from_departure_id', $id))->pluck('id') as $mid) {
            $this->move((int) $mid);
        }
        $map = $map ?: new TransactionMapping(['transaction_type' => PostingRules::DEPARTURE, 'transaction_id' => $id]);
        $result = $this->apply($map, fn () => $this->rules->forDeparture($d), PostingRules::DEPARTURE, $id);
        if ($result === 'none' && $left && ! $map->exists) {
            // E-Ride: a trip that left holding nothing is recorded, so it shows "nothing to post" and the sweep leaves it
            $map->forceFill(['status' => 'none', 'transaction_date' => $left->toDateString(), 'mapped_at' => now()])->save();
        }

        return $result;
    }

    public function statement(string $id, string $kind): string
    {
        $s = BusStatement::find($id);
        if ($s && (string) $s->agency_id !== $this->agencyId()) {
            return 'none';
        }
        $map = TransactionMapping::where('transaction_type', $kind)->where('transaction_id', $id)->first();
        if (! $s) {
            return $map ? $this->apply($map, null, $kind, $id) : 'none';
        }
        $date = $kind === PostingRules::STATEMENT ? Carbon::parse($s->month)->endOfMonth()->toDateString() : ($s->paid_at ? Carbon::parse($s->paid_at)->toDateString() : null);
        if (! $map && $date && $this->isHistory($date)) {
            return 'history';
        }
        $map = $map ?: new TransactionMapping(['transaction_type' => $kind, 'transaction_id' => $id]);

        return $this->apply($map, fn () => $kind === PostingRules::STATEMENT ? $this->rules->forStatement($s) : $this->rules->forStatementPaid($s), $kind, $id);
    }

    // ------------------------------------------------------------------ Income & Expense, Payment Accounts

    /** The model class of each kind, and the column of its own date (for "history" before post_from_date). */
    private const ACCOUNTS_SOURCES = [
        PostingRules::INCOME => [\App\Ledger\Models\Income::class, 'date', 'incomes'],
        PostingRules::EXPENSE => [\App\Ledger\Models\Expense::class, 'date', 'expenses'],
        PostingRules::SUPPLIER_BILL => [\App\Ledger\Models\SupplierBill::class, 'bill_date', 'supplier_bills'],
        PostingRules::SUPPLIER_PAYMENT => [\App\Ledger\Models\SupplierPayment::class, 'payment_date', 'supplier_payments'],
        PostingRules::PAYMENT_TRANSFER => [\App\Ledger\Models\PaymentAccountTransfer::class, 'transfer_date', 'payment_account_transfers'],
        PostingRules::PAYMENT_ACCOUNT_TXN => [\App\Ledger\Models\PaymentAccountTransaction::class, 'transaction_date', 'payment_account_transactions'],
        // paxhitestbed's payroll and tax (App\Ledger\Payroll): a payroll posts when paid (pay_date), a loan when given, a remittance when paid
        PostingRules::PAYROLL => [\App\Ledger\Models\Payroll::class, 'approved_at', 'payrolls'],
        PostingRules::PAYROLL_PAYMENT => [\App\Ledger\Models\Payroll::class, 'pay_date', 'payrolls'],
        PostingRules::STAFF_LOAN => [\App\Ledger\Models\StaffLoan::class, 'given_on', 'staff_loans'],
        PostingRules::TAX_REMITTANCE => [\App\Ledger\Models\TaxRemittance::class, 'payment_date', 'tax_remittances'],
    ];

    /** One record of paxhitestbed's Income & Expense / Payment Accounts (the models are scoped to the open books). */
    public function accountsRecord(string $kind, int $id): string
    {
        [$class, $dateCol] = self::ACCOUNTS_SOURCES[$kind];
        $model = $class::find($id);
        $map = TransactionMapping::where('transaction_type', $kind)->where('transaction_id', (string) $id)->first();
        if (! $model) {
            return $map ? $this->apply($map, null, $kind, $id) : 'none';
        }
        if (! $map && $this->isHistory($model->{$dateCol})) {
            return 'history';
        }
        $map = $map ?: new TransactionMapping(['transaction_type' => $kind, 'transaction_id' => (string) $id]);
        $method = [PostingRules::INCOME => 'forIncome', PostingRules::EXPENSE => 'forExpense', PostingRules::SUPPLIER_BILL => 'forSupplierBill',
            PostingRules::SUPPLIER_PAYMENT => 'forSupplierPayment', PostingRules::PAYMENT_TRANSFER => 'forPaymentAccountTransfer',
            PostingRules::PAYMENT_ACCOUNT_TXN => 'forPaymentAccountTransaction',
            PostingRules::PAYROLL => 'forPayroll', PostingRules::PAYROLL_PAYMENT => 'forPayrollPayment', PostingRules::STAFF_LOAN => 'forStaffLoan', PostingRules::TAX_REMITTANCE => 'forTaxRemittance'][$kind];

        return $this->apply($map, fn () => $this->rules->{$method}($model), $kind, $id);
    }

    /**
     * Catch-up for those records: never looked at, failed, changed since the last sweep, or
     * deleted while their entry stands (changes made without Eloquent).
     */
    private function sweepAccounts(): array
    {
        $bid = Ledger::businessId();
        $stats = [];
        $since = AccountingSetting::getValue('last_sweep_at');
        $since = $since ? Carbon::parse($since)->subMinutes(10) : null;
        foreach (self::ACCOUNTS_SOURCES as $kind => [, $dateCol, $table]) {
            $q = DB::table($table.' as r')->where('r.business_id', $bid)
                ->leftJoin('transaction_mappings as m', function ($j) use ($bid, $kind) {
                    $j->on('m.transaction_id', '=', DB::raw('CAST(r.id AS CHAR)'))->where('m.transaction_type', $kind)->where('m.business_id', $bid);
                })
                ->where(function ($w) use ($since) {
                    $w->whereNull('m.id')->orWhere('m.status', 'failed');
                    if ($since) {
                        $w->orWhere('r.updated_at', '>=', $since);
                    }
                });
            if ($this->postFrom) {
                $q->where('r.'.$dateCol, '>=', $this->postFrom);
            }
            if ($kind === PostingRules::PAYMENT_ACCOUNT_TXN) {
                $q->where(fn ($w) => $w->whereNull('r.reference_type')->orWhere('r.reference_type', 'opening_balance')) // only manual ones post by themselves
                    ->where(fn ($w) => $w->whereNotNull('r.contra_account_id')->orWhereNotNull('m.id')); // and only when the other side is named
            }
            $ids = $q->pluck('r.id')->unique();
            // gone, with an entry still standing
            $gone = DB::table('transaction_mappings as m')->where('m.business_id', $bid)->where('m.transaction_type', $kind)->where('m.status', 'posted')
                ->whereNotExists(fn ($x) => $x->from($table.' as r')->whereColumn(DB::raw('CAST(r.id AS CHAR)'), 'm.transaction_id'))
                ->pluck('m.transaction_id');
            foreach ($ids->merge($gone)->unique() as $id) {
                $r = $this->accountsRecord($kind, (int) $id);
                $stats[$r] = ($stats[$r] ?? 0) + 1;
            }
        }

        return $stats;
    }

    private function isHistory($date): bool
    {
        return $this->postFrom && $date && substr((string) $date, 0, 10) < $this->postFrom;
    }

    /**
     * @param  callable|null  $expected  returns the entry the source should have (or null)
     */
    private function apply(TransactionMapping $map, ?callable $expected, string $kind, int|string $id): string
    {
        $userId = auth()->check() ? auth()->id() : null;
        try {
            $want = $expected ? $expected() : null;
        } catch (\RuntimeException $e) {
            return $this->fail($map, $e->getMessage());
        }

        $fp = null;
        if ($want) {
            // What the document says, on its own date: a closed month moving the posting
            // date to today is not a change, or every later look at an old document (a
            // payment on an October invoice in November) would reverse and repost it.
            $fp = sha1(json_encode([$want['date'], $want['lines']]));
            $want['date'] = $this->postingDate($want['date'], $want);
            if ($map->exists && $map->status === 'posted' && $map->fingerprint === $fp) {
                return 'same';
            }
        } else {
            if (! $map->exists) {
                return 'none';
            }
            if ($map->status !== 'posted' || ! $map->journal_entry_id) {
                $map->forceFill(['status' => 'none', 'error' => null, 'fingerprint' => null])->save();

                return 'none';
            }
        }

        if (! $map->exists) {
            // E-Ride: the entry's reference is the mapping row, so it exists first (outside the
            // transaction: a refused posting must still leave the row to record "failed" on)
            $map->forceFill(['status' => $map->status ?: 'none'])->save();
        }
        try {
            return DB::transaction(function () use ($map, $want, $userId, $kind, $fp) {
                $had = $map->journal_entry_id ? JournalEntry::find($map->journal_entry_id) : null;
                $result = 'posted';
                if ($had && $had->is_posted && ! $had->is_reversed) {
                    LedgerPoster::reverse($had, 'transaction_mapping', $map->id, $userId,
                        $want ? __('ledger.why_changed') : __('ledger.why_removed'));
                    $result = $want ? 'reposted' : 'reversed';
                }
                if ($want) {
                    $entry = LedgerPoster::post([
                        'entry_date' => $want['date'],
                        'journal_type' => $want['journal_type'],
                        'description' => $want['description'],
                        'reference_number' => $want['reference'],
                        'reference_type' => $kind,
                        'reference_id' => $map->id,
                        'is_system_generated' => true,
                    ], $want['lines'], $userId);
                    $dr = collect($want['lines'])->firstWhere('debit', '>', 0);
                    $cr = collect($want['lines'])->firstWhere('credit', '>', 0);
                    $map->fill([
                        'journal_entry_id' => $entry->id,
                        'amount' => $entry->total_debit,
                        'transaction_date' => $want['date'],
                        'description' => $want['description'],
                        'debit_account_id' => $dr['account_id'] ?? null,
                        'credit_account_id' => $cr['account_id'] ?? null,
                        'fingerprint' => $fp,
                        'mapped_at' => now(),
                        'mapped_by' => $userId,
                        'status' => 'posted',
                        'error' => null,
                    ])->save();
                    // E-Ride: bus money also moves in the payment account's book
                    \App\Ledger\ERide\BusBook::sync($kind, (int) $map->id, $want['lines'], $want['date'], $want['description'], $want['reference'] ?? null);
                } else {
                    $map->fill(['journal_entry_id' => null, 'status' => 'none', 'fingerprint' => null, 'error' => null,
                        'mapped_at' => now(), 'mapped_by' => $userId])->save();
                    \App\Ledger\ERide\BusBook::sync($kind, (int) $map->id, null, null, null);
                }

                return $result;
            });
        } catch (\RuntimeException $e) {
            return $this->fail($map, $e->getMessage());
        }
    }

    private function fail(TransactionMapping $map, string $why): string
    {
        $map->fill(['status' => 'failed', 'error' => mb_substr($why, 0, 1000), 'fingerprint' => null])->save();

        return 'failed';
    }

    /**
     * The date an automatic entry goes in: the document's own date while its
     * month is open; once that month is closed, today (the figures of a closed
     * month have been reported, so the change is a new event). A calendar
     * year that does not exist yet is opened automatically, with its months,
     * when the business already keeps books in the year before.
     */
    private function postingDate(string $date, array &$want): string
    {
        $this->ensureYear($date);
        if (LedgerPoster::isOpenDate($date)) {
            return $date;
        }
        $year = FiscalYear::forDate($date);
        $period = FiscalYear::periodForDate($date);
        if (($year && $year->is_closed) || ($period && $period->is_closed)) {
            $today = Carbon::today()->toDateString();
            $this->ensureYear($today);
            $want['description'] = mb_substr($want['description'].' ('.__('ledger.dated', ['date' => Carbon::parse($date)->format('d/m/Y')]).')', 0, 250);

            return $today;
        }

        return $date; // no year: LedgerPoster refuses with the reason, and the mapping is marked failed
    }

    private function ensureYear(string $date): void
    {
        if (FiscalYear::forDate($date)) {
            return;
        }
        $y = (int) substr($date, 0, 4);
        $before = FiscalYear::whereYear('end_date', $y - 1)->exists();
        if (! $before) {
            return;
        }
        $fy = FiscalYear::create(['name' => (string) $y, 'start_date' => $y.'-01-01', 'end_date' => $y.'-12-31', 'is_active' => false]);
        $fy->generatePeriods(null);
    }

    // ------------------------------------------------------------------ sweep

    /**
     * Catch up: records never posted, failed ones, trips that changed since the
     * last sweep (with their moves), cancelled trips whose time has now passed,
     * and statements that changed.
     */
    public function sweep(): array
    {
        // E-Ride: the payment account books touched are rebuilt once, at the end
        return PaymentBook::deferRecompute(fn () => $this->sweepRun());
    }

    private function sweepRun(): array
    {
        $bid = Ledger::businessId();
        $agency = $this->agencyId();
        $stats = [];
        // paxhitestbed's Income & Expense and Payment Accounts: on every set of books, a head office's too
        foreach ($this->sweepAccounts() as $r => $n) {
            $stats[$r] = ($stats[$r] ?? 0) + $n;
        }
        if (! $agency) {
            // a head office's own books: what it collects and pays for its branches (App\Ledger\ERide\HeadOffice)
            foreach (\App\Ledger\ERide\HeadOffice::sweep($this) as $r => $n) {
                $stats[$r] = ($stats[$r] ?? 0) + $n;
            }

            return $stats;
        }
        $since = AccountingSetting::getValue('last_sweep_at');
        $since = $since ? Carbon::parse($since)->subMinutes(10) : null;
        $from = $this->postFrom ?: '1970-01-01';
        $count = function ($r) use (&$stats) {
            $stats[$r] = ($stats[$r] ?? 0) + 1;
        };
        $started = now();
        $unmapped = function ($q, string $col, string $type) use ($bid) {
            return $q->leftJoin('transaction_mappings as m', function ($j) use ($bid, $col, $type) {
                $j->on('m.transaction_id', '=', $col)->where('m.transaction_type', $type)->where('m.business_id', $bid);
            });
        };

        // money moves: not mapped yet, or failed
        $q = $unmapped(DB::table('bus_money_moves as mv')->where('mv.agency_id', $agency)->whereNull('mv.skipped')
            ->where('mv.at', '>=', $from), 'mv.id', PostingRules::MOVE)
            ->where(fn ($w) => $w->whereNull('m.id')->orWhere('m.status', 'failed'))->orderBy('mv.id');
        foreach ($q->pluck('mv.id') as $id) {
            $count($this->move((int) $id));
        }

        // trips: gone and not mapped yet, failed, or changed since the last sweep (a trip running again included)
        $q = $unmapped(DB::table('bus_departures as d')->where('d.agency_id', $agency)->where('d.date', '>=', Carbon::parse($from)->subDay()->toDateString()),
            'd.id', PostingRules::DEPARTURE)
            ->where(function ($w) use ($since) {
                $w->where(fn ($x) => $x->whereNull('m.id')->whereIn('d.status', [BusDeparture::DEPARTED, BusDeparture::CANCELLED]))
                    ->orWhere('m.status', 'failed');
                if ($since) {
                    $w->orWhere('d.updated_at', '>=', $since);
                }
            })->orderBy('d.date')->orderBy('d.time');
        foreach ($q->pluck('d.id') as $id) {
            $count($this->departure((string) $id));
        }

        // E-Ride's statements: owed, then paid
        foreach ([PostingRules::STATEMENT, PostingRules::STATEMENT_PAID] as $kind) {
            $q = $unmapped(DB::table('bus_statements as s')->where('s.agency_id', $agency)->where('s.total', '>', 0)
                ->when($kind === PostingRules::STATEMENT_PAID, fn ($x) => $x->whereNotNull('s.paid_at')), 's.id', $kind)
                ->where(function ($w) use ($since) {
                    $w->whereNull('m.id')->orWhere('m.status', 'failed');
                    if ($since) {
                        $w->orWhere('s.updated_at', '>=', $since);
                    }
                });
            foreach ($q->pluck('s.id') as $id) {
                $count($this->statement((string) $id, $kind));
            }
        }

        AccountingSetting::setValue('last_sweep_at', $started->toDateTimeString());

        return $stats;
    }

    // ------------------------------------------------------------------ opening

    /**
     * Opening balances at go-live (posinnova posts what customers and suppliers
     * owe; a bus branch owes its passengers): the tickets already paid for trips
     * that have not left by the start date (477, per trip), and the refunds
     * still owed (4198), worked out with the same posting rules, posted as one
     * entry against the opening-balance suspense account (4711). The money
     * itself (cash, MoMo, bank) is typed by the accountant as an opening journal
     * entry, as in posinnova.
     */
    public function openingFromHistory(?string $userId = null): ?JournalEntry
    {
        if (! $this->postFrom || AccountingSetting::getValue('opening_entry_id')) {
            return null;
        }
        $agency = $this->agencyId();
        $held = DefaultAccount::id('tickets_held');
        $owed = DefaultAccount::id('refunds_owed');
        $start = Carbon::parse($this->postFrom)->startOfDay();
        $sum = []; // account|departure|channel => net credit
        $skipped = [];
        if ($agency) {
            BusMoneyMove::where('agency_id', $agency)->whereNull('skipped')->where('at', '<', $start)->orderBy('id')
                ->chunk(500, function ($rows) use (&$sum, &$skipped, $held, $owed, $start) {
                    foreach ($rows as $m) {
                        try {
                            $e = $this->rules->forMove($m);
                        } catch (\RuntimeException $ex) {
                            $skipped[] = ['move', $m->id, $ex->getMessage()];
                            continue;
                        }
                        foreach ($e['lines'] ?? [] as $l) {
                            if ((int) $l['account_id'] === (int) $owed) {
                                $k = $owed.'||';
                            } elseif ((int) $l['account_id'] === (int) $held && ! empty($l['departure_id'])) {
                                // only trips still to leave at the start date
                                $d = BusDeparture::find($l['departure_id']);
                                $left = $this->rules->leftAt($d);
                                if ($left && $left->lt($start)) {
                                    continue;
                                }
                                $k = $held.'|'.$l['departure_id'].'|'.($l['channel'] ?? '');
                            } else {
                                continue;
                            }
                            $sum[$k] = ($sum[$k] ?? 0) + $l['credit'] - $l['debit'];
                        }
                    }
                });
        }
        AccountingSetting::setValue('opening_skipped', array_slice($skipped, 0, 200), 'json');
        AccountingSetting::setValue('opening_skipped_count', count($skipped), 'integer');

        $lines = [];
        $net = 0;
        foreach ($sum as $k => $v) {
            $v = round($v, 2);
            if (abs($v) < 0.01) {
                continue;
            }
            [$acc, $dep, $channel] = explode('|', $k);
            $d = $dep !== '' ? BusDeparture::find($dep) : null;
            $lines[] = ['account_id' => (int) $acc, 'debit' => max(0, -$v), 'credit' => max(0, $v),
                'description' => __('ledger.l_opening_balance')] + $this->rules->tags($d, $channel !== '' ? $channel : null);
            $net += $v;
        }
        if (empty($lines)) {
            AccountingSetting::setValue('opening_entry_id', 0);

            return null;
        }
        $offset = DefaultAccount::id('opening_balance');
        $net = round($net, 2);
        $lines[] = ['account_id' => $offset, 'debit' => max(0, $net), 'credit' => max(0, -$net),
            'description' => __('ledger.l_opening_offset')];

        $entry = LedgerPoster::post([
            'entry_date' => $this->postFrom,
            'journal_type' => 'opening',
            'description' => __('ledger.opening_entry_desc', ['date' => Carbon::parse($this->postFrom)->format('d/m/Y')]),
            'reference_type' => 'opening_balances',
            'reference_id' => Ledger::businessId(),
            'is_system_generated' => true,
        ], $lines, $userId);
        AccountingSetting::setValue('opening_entry_id', $entry->id);

        return $entry;
    }
}
