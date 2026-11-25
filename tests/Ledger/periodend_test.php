<?php
/*
 * Period-end screens of the accounting module (posinnova's, on a bus branch's books), end to end
 * through the real routes (HTTP kernel, middleware, controllers), all rolled back:
 *  1. Bank reconciliation of 5521 MTN Mobile Money: tick statement lines, post a bank charge from
 *     the screen through the review, complete when the difference is nil; a second statement
 *     carries what was left unticked, with interest posted the same way.
 *  2. Recurring entries: monthly rent (Dr 622 / Cr 5521) processed with the other due ones,
 *     a second template processed alone as a draft and posted, the upcoming list.
 *  3. Year-end closing: checklist, the closing-stock step at 0 (bus branches keep no stock),
 *     the closing entry to the result and retained earnings, approval (owner only: the
 *     accountant is refused), reversal with a reason.
 * Every posting is refused without the review tick (reviewed=1).
 *
 * Run: php tests/Ledger/periodend_test.php
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingPeriod;
use App\Ledger\Models\BankReconciliation;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Models\JournalEntryLine;
use App\Ledger\Models\RecurringJournalEntry;
use App\Ledger\Models\YearEndClosing;
use App\Ledger\Services\LedgerSync;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAgency, BusDeparture, BusDestination, BusDropPoint, BusMoneyMove, BusStaff, BusVehicle};
use Modules\UserManagement\Entities\User;

$pass = 0; $fail = 0; $fails = [];
$ok = function ($c, $label) use (&$pass, &$fail, &$fails) { if ($c) { $pass++; } else { $fail++; $fails[] = $label; } print(($c ? 'PASS ' : 'FAIL ') . $label . "\n"); };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;

$year = (int)now()->year;
DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    \App\Ledger\LedgerObserver::forget();
    $mgr = accTestUser('Period Manager'); $agent = accTestUser('Period Agent'); $accountant = accTestUser('Period Accountant');

    $name = 'Period Branch Bamenda';
    BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => $name, 'slug' => 'period-' . Str::lower(Str::random(5)), 'origin_city' => 'Bamenda',
        'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => [], 'commission_counter_pct' => 3, 'commission_app_pct' => 5]);
    $A = BusAgency::where('name', $name)->first();
    BusStaff::create(['agency_id' => $A->id, 'user_id' => $mgr->id, 'role' => BusStaff::MANAGER, 'active' => true]);
    BusStaff::create(['agency_id' => $A->id, 'user_id' => $agent->id, 'role' => BusStaff::COUNTER, 'active' => true]);
    BusStaff::create(['agency_id' => $A->id, 'user_id' => $accountant->id, 'role' => BusStaff::ACCOUNTANT, 'active' => true]);
    BusDestination::forceCreate(['agency_id' => $A->id, 'city' => 'Yaounde', 'active' => true, 'duration_minutes' => 360]);
    $dest = BusDestination::where('agency_id', $A->id)->first();
    BusDropPoint::forceCreate(['destination_id' => $dest->id, 'name' => 'Main office', 'fare_classic' => 5000, 'active' => true, 'sort' => 1]);
    BusVehicle::forceCreate(['agency_id' => $A->id, 'registration' => 'PER ' . strtoupper(Str::random(3)), 'name' => 'Coach', 'class' => 'classic', 'status' => 'ready', 'total_seats' => 10]);
    $bus = BusVehicle::where('agency_id', $A->id)->first();
    BusDeparture::forceCreate(['agency_id' => $A->id, 'destination_id' => $dest->id, 'class' => 'classic', 'kind' => 'fixed',
        'date' => today()->toDateString(), 'time' => '10:00:00', 'vehicle_id' => $bus->id,
        'seat_plan' => ['seats' => array_map('strval', range(1, 10))], 'capacity' => 10, 'status' => 'scheduled']);
    $dep = BusDeparture::where('agency_id', $A->id)->first();

    // ---- The HTTP client (as in bus_ledger_test.php).
    $session = $app['session']->driver();
    $call = function (string $method, string $url, array $data = [], ?User $as = null, bool $json = false) use ($app, $kernel, $session) {
        $req = Request::create($url, $method, $data, [], [], ['HTTP_ACCEPT' => $json ? 'application/json' : 'text/html']);
        if ($json) {
            $req->headers->set('X-Requested-With', 'XMLHttpRequest');
        }
        $app['auth']->guard('web')->setUser($as);
        $req->setLaravelSession($session);
        $session->put('_token', 'tok');
        $req->headers->set('X-CSRF-TOKEN', 'tok');
        $res = $kernel->handle($req);
        if ($res->getStatusCode() >= 500) {
            echo '   500 on ', $url, ': ', $res->exception?->getMessage(), ' @ ', basename((string)$res->exception?->getFile()), ':', $res->exception?->getLine(), "\n";
        }
        return $res;
    };
    $to = fn($res) => (string)$res->headers->get('Location');
    $flash = fn() => (string)($session->get('status')['msg'] ?? '');
    $P = '/partner/accounting/';

    // ---- Open the books from the branch portal and set them up (review).
    $call('GET', '/partner/bus-agency/accounting', [], $mgr);
    $bid = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $A->id)->value('id');
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgr);
    $ok($bid > 0 && Ledger::isSetUp($bid), 'books of the branch set up');

    $acc = fn(string $code) => (int)Ledger::forBusiness($bid, fn() => ChartOfAccount::where('account_code', $code)->value('id'));
    $bal = fn(string $code) => round((float)Ledger::forBusiness($bid, fn() => JournalEntryLine::where('account_id', ChartOfAccount::where('account_code', $code)->value('id'))
        ->whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->selectRaw('COALESCE(SUM(debit - credit), 0) as b')->value('b')), 2);
    $in = fn(callable $f) => Ledger::forBusiness($bid, $f);
    $move = function (array $f) use ($A, $dep) {
        $m = BusMoneyMove::create($f + ['agency_id' => $A->id, 'booking_id' => (string)Str::uuid(), 'departure_id' => (string)$dep->id, 'channel' => 'counter', 'at' => now()]);
        LedgerSync::flush();
        return $m;
    };

    // Ticket money: two MTN sales (5 000 and 3 000) and 4 000 cash, the trip leaves: 12 000 of sales.
    $move(['kind' => 'sale', 'method' => 'momo', 'amount' => 5000, 'staff_user_id' => $agent->id]);
    $move(['kind' => 'sale', 'method' => 'momo', 'amount' => 3000, 'staff_user_id' => $agent->id]);
    $move(['kind' => 'sale', 'method' => 'cash', 'amount' => 4000, 'staff_user_id' => $agent->id]);
    $dep->update(['status' => 'departed', 'departed_at' => now()->addSecond()]);
    LedgerSync::flush();
    $ok($eq($bal('5521'), 8000) && $eq($bal('706'), -12000), 'ticket money in the books: 5521 = 8 000, 706 = 12 000 of sales');

    // ================= 1. Bank reconciliation =================
    $m5521 = $acc('5521');
    $r = $call('GET', $P . 'bank-reconciliation', [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'MTN Mobile Money'), 'Bank reconciliation list opens and offers 5521 (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'bank-reconciliation/create?account_id=' . $m5521, [], $mgr);
    $ok($r->getStatusCode() === 200, 'new reconciliation form opens (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'bank-reconciliation-summary', [], $mgr);
    $ok($r->getStatusCode() === 302 && str_contains($to($r), 'bank-reconciliation'), 'summary goes to the list');

    // The statement: the 5 000 is on it, the 3 000 not yet, and 200 of charges: 4 800.
    $r = $call('POST', $P . 'bank-reconciliation', ['chart_of_account_id' => $m5521, 'statement_date' => today()->toDateString(),
        'statement_closing_balance' => 4800, 'statement_reference' => 'MTN-NOV'], $mgr);
    $rec = $in(fn() => BankReconciliation::latest('id')->first());
    $ok($rec && $rec->status === 'in_progress' && $eq($rec->book_balance, 8000), 'reconciliation started: book balance 8 000 (' . $to($r) . ' ' . $flash() . ')');
    $items = $rec ? $in(fn() => $rec->items()->orderBy('amount', 'desc')->get()) : collect();
    $ok($items->count() === 2 && $eq($items[0]->amount, 5000) && $eq($items[1]->amount, 3000), 'both MTN movements are listed');
    $r = $call('GET', $P . 'bank-reconciliation/' . $rec->id, [], $mgr);
    $ok($r->getStatusCode() === 200, 'reconciliation screen opens (' . $r->getStatusCode() . ')');
    $r = $call('POST', $P . 'bank-reconciliation', ['chart_of_account_id' => $m5521, 'statement_date' => today()->toDateString(), 'statement_closing_balance' => 1], $mgr);
    $ok($in(fn() => BankReconciliation::count()) === 1, 'a second one on the same account while one is open is refused');

    $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/ticks', ['cleared' => [$items[0]->id]], $mgr);
    $rec = $in(fn() => $rec->fresh());
    $ok($eq($rec->difference, 200) && $eq($rec->outstanding_deposits, 3000), 'the 5 000 ticked: 3 000 in transit, difference 200 (the charges)');
    $call('POST', $P . 'bank-reconciliation-item/' . $items[1]->id . '/clear', [], $mgr);
    $ok($eq($in(fn() => $rec->fresh())->difference, 3200), 'ticking the 3 000 too: nothing in transit, difference 3 200');
    $call('POST', $P . 'bank-reconciliation-item/' . $items[1]->id . '/unclear', [], $mgr);
    $ok($eq($in(fn() => $rec->fresh())->difference, 200), 'unticking it: back to 200');
    $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/update-balance', ['statement_closing_balance' => 4800, 'statement_reference' => 'MTN-NOV-1'], $mgr);
    $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/recalculate', [], $mgr);
    $ok($in(fn() => $rec->fresh())->statement_reference === 'MTN-NOV-1', 'statement balance and reference updated, recalculated');

    $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/complete', ['reviewed' => 1], $mgr);
    $ok($in(fn() => $rec->fresh())->status === 'in_progress', 'completing with a difference is refused (' . $flash() . ')');

    $adj = ['kind' => 'bank_charge', 'amount' => 200, 'date' => today()->toDateString(), 'account_id' => $acc('631'), 'description' => 'MTN fees'];
    $r = $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/preview/adjustment', $adj, $mgr, true);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'ap-preview') && str_contains($r->getContent(), '631'), 'bank charge review renders with the entry (' . $r->getStatusCode() . ')');
    $r = $call('POST', $P . 'review/dry-run', $adj + ['_token' => 'tok', '_review_target' => $P . 'bank-reconciliation/' . $rec->id . '/add-adjustment'], $mgr, true);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), '631') && $eq($bal('631'), 0), 'the review dry-run shows the charge entry and saves nothing');
    $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/add-adjustment', $adj, $mgr);
    $ok($eq($bal('631'), 0), 'bank charge without the review tick is refused');
    $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/add-adjustment', $adj + ['reviewed' => 1], $mgr);
    $rec = $in(fn() => $rec->fresh());
    $ok($eq($bal('631'), 200) && $eq($bal('5521'), 7800), 'bank charge posted: Dr 631 200 / Cr 5521 200');
    $ok($eq($rec->difference, 0) && $eq($rec->bank_charges, 200) && $in(fn() => $rec->items()->where('item_type', 'bank_charge')->where('is_cleared', true)->count()) === 1,
        'the charge is ticked straight away: difference nil');
    $r = $call('GET', $P . 'bank-reconciliation/' . $rec->id . '/preview/complete', [], $mgr, true);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'ap-preview'), 'complete review renders');
    $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/complete', [], $mgr);
    $ok($in(fn() => $rec->fresh())->status === 'in_progress', 'completing without the review tick is refused');
    $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/complete', ['reviewed' => 1], $mgr);
    $rec = $in(fn() => $rec->fresh());
    $ok($rec->status === 'completed' && (string)$rec->reconciled_by === (string)$mgr->id, 'reconciliation completed by the manager (uuid kept)');
    $r = $call('GET', $P . 'bank-reconciliation/' . $rec->id, [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Period Manager'), 'the completed one shows who completed it');
    $call('POST', $P . 'bank-reconciliation/' . $rec->id . '/ticks', ['cleared' => []], $mgr);
    $ok($in(fn() => $rec->items()->where('is_cleared', true)->count()) === 2, 'a completed reconciliation is locked');
    $call('DELETE', $P . 'bank-reconciliation/' . $rec->id, [], $mgr);
    $ok($in(fn() => BankReconciliation::find($rec->id)) !== null, 'and cannot be deleted');

    // ================= 2. Recurring entries =================
    $r = $call('GET', $P . 'recurring-entries', [], $mgr);
    $ok($r->getStatusCode() === 200, 'Recurring entries list opens (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'recurring-entries/create', [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), '622'), 'new recurring entry form opens with the chart');
    $tpl = fn(string $name, int $amount, string $cr, bool $auto) => [
        'template_name' => $name, 'description' => $name, 'journal_type' => 'general', 'frequency' => 'monthly', 'day_of_month' => today()->day,
        'start_date' => today()->toDateString(), 'auto_post' => $auto ? 1 : null,
        'lines' => [['account_id' => $acc('622'), 'debit' => $amount, 'credit' => 0, 'description' => $name],
            ['account_id' => $acc($cr), 'debit' => 0, 'credit' => $amount, 'description' => $name]],
    ];
    $call('POST', $P . 'recurring-entries', $tpl('Office rent', 6000, '5521', true), $mgr);
    $rent = $in(fn() => RecurringJournalEntry::where('name', 'Office rent')->first());
    $ok($rent && $rent->is_active && $rent->auto_post && $eq($rent->total_debit, 6000) && (string)$rent->created_by === (string)$mgr->id, 'monthly rent template created (Dr 622 / Cr 5521, posts itself)');
    $bad = $tpl('Unbalanced', 1000, '5521', true);
    $bad['lines'][1]['credit'] = 900;
    $call('POST', $P . 'recurring-entries', $bad, $mgr);
    $ok(!$in(fn() => RecurringJournalEntry::where('name', 'Unbalanced')->exists()), 'an unbalanced template is refused');
    $call('POST', $P . 'recurring-entries', $tpl('Night guard', 20000, '571', false), $mgr);
    $guard = $in(fn() => RecurringJournalEntry::where('name', 'Night guard')->first());
    $ok($guard && !$guard->auto_post, 'second template (draft each time)');
    $r = $call('GET', $P . 'recurring-entries/' . $rent->id, [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Period Manager'), 'template screen opens and shows who created it');
    $r = $call('GET', $P . 'recurring-entries/' . $rent->id . '/edit', [], $mgr);
    $ok($r->getStatusCode() === 200, 'template edit form opens');

    // The guard alone first (one occurrence), then everything still due (the rent).
    $call('POST', $P . 'recurring-entries/' . $guard->id . '/process', [], $mgr);
    $ok($in(fn() => JournalEntry::where('reference_type', 'recurring_entry')->count()) === 0, 'processing one without the review tick is refused');
    $r = $call('POST', $P . 'review/dry-run', ['_token' => 'tok', '_review_target' => $P . 'recurring-entries/' . $guard->id . '/process'], $mgr, true);
    $ok(str_contains($r->getContent(), '622') && $in(fn() => JournalEntry::where('reference_type', 'recurring_entry')->count()) === 0, 'its review shows the entry and saves nothing');
    $call('POST', $P . 'recurring-entries/' . $guard->id . '/process', ['reviewed' => 1], $mgr);
    $gEntry = $in(fn() => JournalEntry::where('reference_type', 'recurring_entry')->where('reference_id', $guard->id)->first());
    $ok($gEntry && !$gEntry->is_posted && $eq($bal('622'), 0), 'guard entry written as a draft (not posted)');
    if ($gEntry) {
        $call('POST', $P . 'journal-entries/' . $gEntry->id . '/post', ['reviewed' => 1], $mgr);
        $ok($eq($bal('622'), 20000) && $eq($bal('571'), -20000), 'the draft is posted through the review: Dr 622 / Cr 571 20 000');
    }
    $call('POST', $P . 'recurring-entries-process-all', [], $mgr);
    $ok($eq($bal('5521'), 7800), 'process all without the review tick is refused');
    $call('POST', $P . 'recurring-entries-process-all', ['reviewed' => 1], $mgr);
    $rent = $in(fn() => $rent->fresh());
    $ok($eq($bal('622'), 26000) && $eq($bal('5521'), 1800), 'process all: the rent is posted, Dr 622 / Cr 5521 6 000');
    $ok((int)$rent->occurrences_completed === 1 && $rent->next_run_date->toDateString() === today()->addMonthNoOverflow()->toDateString(), 'the rent runs again next month (' . $rent->next_run_date->toDateString() . ')');
    $call('POST', $P . 'recurring-entries-process-all', ['reviewed' => 1], $mgr);
    $ok($eq($bal('622'), 26000), 'nothing due any more: nothing posted twice');
    $r = $call('GET', $P . 'recurring-entries-upcoming?days=40', [], $mgr, true);
    $up = json_decode($r->getContent(), true);
    $ok($r->getStatusCode() === 200 && collect($up['data'] ?? [])->pluck('name')->contains('Office rent'), 'upcoming lists the rent');
    $call('POST', $P . 'recurring-entries/' . $guard->id . '/pause', [], $mgr);
    $ok(!$in(fn() => $guard->fresh())->is_active, 'guard paused');
    $call('POST', $P . 'recurring-entries/' . $guard->id . '/resume', [], $mgr);
    $ok($in(fn() => $guard->fresh())->is_active, 'guard resumed');
    $next = $in(fn() => $guard->fresh())->next_run_date->toDateString();
    $call('POST', $P . 'recurring-entries/' . $guard->id . '/skip-next', [], $mgr);
    $ok($in(fn() => $guard->fresh())->next_run_date->toDateString() > $next, 'skip next moves the guard on a month');
    $call('POST', $P . 'recurring-entries/' . $rent->id . '/duplicate', [], $mgr);
    $copy = $in(fn() => RecurringJournalEntry::where('name', 'Office rent')->where('id', '!=', $rent->id)->first());
    $ok($copy && (int)$copy->occurrences_completed === 0 && $copy->lines()->count() === 2, 'duplicate makes a fresh copy');
    if ($copy) {
        $call('DELETE', $P . 'recurring-entries/' . $copy->id, [], $mgr);
        $ok(!$in(fn() => RecurringJournalEntry::find($copy->id)), 'the copy is deleted');
    }
    $r = $call('GET', $P . 'recurring-entries', [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Office rent'), 'the list shows the templates');

    // ---- Second statement at the end of the year: the 3 000 left unticked, the rent, 100 of interest.
    Carbon::setTestNow(Carbon::create($year, 12, 31, 12));
    $call('POST', $P . 'bank-reconciliation', ['chart_of_account_id' => $m5521, 'statement_date' => today()->toDateString(), 'statement_closing_balance' => 1900], $mgr);
    $rec2 = $in(fn() => BankReconciliation::where('id', '!=', $rec->id)->latest('id')->first());
    $amounts = $rec2 ? $in(fn() => $rec2->items()->pluck('amount')->map(fn($a) => (int)$a)->sort()->values()->all()) : [];
    $ok($rec2 && $amounts === [3000, 6000], 'second statement offers the 3 000 left unticked and the rent, not what was ticked (' . json_encode($amounts) . ')');
    if ($rec2) {
        $call('POST', $P . 'bank-reconciliation/' . $rec2->id . '/ticks', ['cleared' => $in(fn() => $rec2->items()->pluck('id')->all())], $mgr);
        $call('POST', $P . 'bank-reconciliation/' . $rec2->id . '/add-adjustment', ['kind' => 'bank_interest', 'amount' => 100, 'date' => today()->toDateString(), 'account_id' => $acc('771'), 'reviewed' => 1], $mgr);
        $rec2 = $in(fn() => $rec2->fresh());
        $ok($eq($bal('771'), -100) && $eq($bal('5521'), 1900) && $eq($rec2->difference, 0), 'interest posted from the screen: Dr 5521 / Cr 771 100, difference nil');
        $call('POST', $P . 'bank-reconciliation/' . $rec2->id . '/complete', ['reviewed' => 1], $mgr);
        $ok($in(fn() => $rec2->fresh())->status === 'completed', 'second reconciliation completed');
        $call('POST', $P . 'bank-reconciliation', ['chart_of_account_id' => $m5521, 'statement_date' => today()->subDays(3)->toDateString(), 'statement_closing_balance' => 0], $mgr);
        $ok($in(fn() => BankReconciliation::count()) === 2, 'a statement dated before the last completed one is refused');
    }
    // A draft one deleted (only an open one can be).
    $call('POST', $P . 'bank-reconciliation', ['chart_of_account_id' => $acc('5712'), 'statement_date' => today()->toDateString(), 'statement_closing_balance' => 0], $mgr);
    $rec3 = $in(fn() => BankReconciliation::where('chart_of_account_id', $acc('5712'))->first());
    if ($rec3) {
        $call('DELETE', $P . 'bank-reconciliation/' . $rec3->id, [], $mgr);
    }
    $ok($rec3 && !$in(fn() => BankReconciliation::find($rec3->id)), 'an open reconciliation (agent tills 5712) can be deleted');

    // ================= 3. Year-end closing =================
    Carbon::setTestNow(Carbon::create($year + 1, 1, 10, 9));
    $fy = $in(fn() => FiscalYear::whereYear('start_date', $year)->first());
    $dec = $in(fn() => AccountingPeriod::where('fiscal_year_id', $fy->id)->orderByDesc('start_date')->first());
    foreach ($in(fn() => AccountingPeriod::where('fiscal_year_id', $fy->id)->where('id', '!=', $dec->id)->orderBy('start_date')->get()) as $p) {
        $call('POST', $P . 'accounting-periods/' . $p->id . '/close', ['reviewed' => 1], $mgr);
    }
    $ok($in(fn() => AccountingPeriod::where('fiscal_year_id', $fy->id)->where('is_closed', true)->count()) === 11, 'January to November closed, December open');

    $r = $call('GET', $P . 'year-end-closing', [], $mgr);
    $ok($r->getStatusCode() === 200, 'Year-end closing list opens (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'year-end-closing/create', [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), '121') && str_contains($r->getContent(), '130'), 'new closing form offers 121 and 130');
    $call('POST', $P . 'year-end-closing', ['fiscal_year_id' => $fy->id, 'closing_date' => "$year-12-31",
        'retained_earnings_account_id' => $acc('121'), 'income_summary_account_id' => $acc('130')], $mgr);
    $yc = $in(fn() => YearEndClosing::latest('id')->first());
    $ok($yc && $yc->status === 'draft' && (string)$yc->created_by === (string)$mgr->id, 'closing created as a draft (' . $flash() . ')');
    $call('POST', $P . 'year-end-closing/' . $yc->id . '/start', [], $mgr);
    $yc = $in(fn() => $yc->fresh());
    $ok($yc->status === 'in_progress', 'closing started');
    $r = $call('GET', $P . 'year-end-closing/' . $yc->id, [], $mgr);
    $ok($r->getStatusCode() === 200, 'closing screen opens with its checks (' . $r->getStatusCode() . ')');
    $html = $r->getContent();
    $ok(!str_contains($html, 'PosInnova'), 'no PosInnova wording on the closing screen');

    $checks = $in(fn() => app(\App\Ledger\Services\YearEndClosingService::class)->checks($yc->fresh()));
    $blocking = array_values(array_map(fn($c) => $c['key'] . ': ' . $c['detail'], array_filter($checks, fn($c) => !$c['ok'] && $c['blocking'])));
    $ok($blocking === [], 'no blocking check before the closing stock is entered (' . implode(' | ', $blocking) . ')');
    $ok($in(fn() => app(\App\Ledger\Services\YearEndClosingService::class)->stockValuation("$year-12-31")) === 0.0, "posinnova's stock valuation (no TransactionUtil here) is 0, quietly");

    $call('POST', $P . 'year-end-closing/' . $yc->id . '/generate-entries', ['reviewed' => 1], $mgr);
    $ok($in(fn() => $yc->fresh())->status === 'in_progress', 'closing entries refused while the checklist is not complete');
    $call('POST', $P . 'year-end-closing/' . $yc->id . '/update-checklist', ['checklist' => array_fill_keys(YearEndClosing::CHECKLIST_ITEMS, ['completed' => 1])], $mgr);
    $yc = $in(fn() => $yc->fresh());
    $ok($yc->isChecklistComplete() && str_contains((string)($yc->checklist['trial_balance_reviewed']['completed_by'] ?? ''), 'Period Manager'), 'checklist ticked, with who ticked it');
    $call('POST', $P . 'year-end-closing/' . $yc->id . '/stock', ['closing_stock_value' => 0], $mgr);
    $yc = $in(fn() => $yc->fresh());
    $ok($yc->closing_stock_value !== null && $eq($yc->closing_stock_value, 0) && $eq($yc->stock_valuation, 0), 'closing stock step saved at 0 (' . $flash() . ')');

    // The year's result before closing: 12 000 + 100 - 200 - 26 000 = -14 100.
    $net = -round((float)$in(fn() => DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
        ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')->where('l.business_id', Ledger::businessId())->where('e.is_posted', 1)
        ->where('e.fiscal_year_id', $fy->id)->whereRaw("LEFT(a.account_code, 1) IN ('6','7','8')")->sum(DB::raw('l.debit - l.credit'))), 2);
    $ok($eq($net, -14100), "the year's result is a loss of 14 100 ($net)");

    $call('POST', $P . 'year-end-closing/' . $yc->id . '/generate-entries', [], $mgr);
    $ok($in(fn() => $yc->fresh())->status === 'in_progress', 'closing entries without the review tick are refused');
    $r = $call('POST', $P . 'review/dry-run', ['_token' => 'tok', '_review_target' => $P . 'year-end-closing/' . $yc->id . '/generate-entries'], $mgr, true);
    $ok(str_contains($r->getContent(), '130') && $in(fn() => $yc->fresh())->status === 'in_progress', 'the review shows the closing entry and saves nothing');
    $call('POST', $P . 'year-end-closing/' . $yc->id . '/generate-entries', ['reviewed' => 1], $mgr);
    $yc = $in(fn() => $yc->fresh());
    $ok($yc->status === 'pending_approval' && $yc->closing_journal_entry_id && !$yc->stock_journal_entry_id, 'closing entry posted, no stock entry (no stock), waiting for approval (' . $flash() . ')');
    $ok($eq($yc->net_income, -14100) && $eq($yc->total_revenue, 12100) && $eq($yc->total_expenses, 26200), 'revenue 12 100, expenses 26 200, result -14 100');
    foreach (['706', '771', '622', '631'] as $c) {
        $ok($eq($bal($c), 0), "$c brought to zero by the closing entry");
    }
    $ok($eq($bal('130'), 0) && $eq($bal('121'), 14100), 'the result passes through 130 to 121 (a loss: 121 debit 14 100)');
    $ce = $in(fn() => JournalEntry::with('lines')->find($yc->closing_journal_entry_id));
    $ok($ce && $ce->is_posted && $eq($ce->lines->sum('debit'), $ce->lines->sum('credit')) && $ce->entry_date->toDateString() === "$year-12-31", 'the closing entry balances and is dated 31/12');

    // Approval: owner only. The accountant opens the same books and is refused.
    $call('GET', '/partner/bus-agency/accounting', [], $accountant);
    $r = $call('GET', $P . 'year-end-closing/' . $yc->id, [], $accountant);
    $ok($r->getStatusCode() === 200, 'the accountant opens the closing (' . $r->getStatusCode() . ')');
    $r = $call('POST', $P . 'year-end-closing/' . $yc->id . '/approve', ['reviewed' => 1], $accountant);
    $ok($r->getStatusCode() === 403 && $in(fn() => $yc->fresh())->status === 'pending_approval', 'the accountant cannot approve a year-end closing (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'year-end-closing/' . $yc->id . '/preview/reverse', [], $accountant, true);
    $ok($r->getStatusCode() === 403, 'nor review a reversal');
    $call('GET', '/partner/bus-agency/accounting', [], $mgr);
    $call('POST', $P . 'year-end-closing/' . $yc->id . '/approve', [], $mgr);
    $ok($in(fn() => $yc->fresh())->status === 'pending_approval', 'approval without the review tick is refused');
    $call('POST', $P . 'year-end-closing/' . $yc->id . '/approve', ['reviewed' => 1], $mgr);
    $yc = $in(fn() => $yc->fresh());
    $fy = $in(fn() => $fy->fresh());
    $ok($yc->status === 'completed' && (string)$yc->approved_by === (string)$mgr->id && $fy->is_closed && $in(fn() => $dec->fresh())->is_closed,
        'the manager approves: the year and December are closed (' . $flash() . ')');
    $r = $call('GET', $P . 'year-end-closing/' . $yc->id, [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Period Manager'), 'the completed closing shows who prepared and approved it');
    $ok($in(fn() => app(\App\Ledger\Services\YearEndClosingService::class)->checks($yc)) !== null, 'checks still answer on a closed year');

    // Nothing more can be posted into the closed year.
    $call('POST', $P . 'bank-reconciliation', ['chart_of_account_id' => $m5521, 'statement_date' => today()->toDateString(), 'statement_closing_balance' => 1900], $mgr);
    $rec4 = $in(fn() => BankReconciliation::where('chart_of_account_id', $m5521)->where('status', 'in_progress')->first());
    if ($rec4) {
        $call('POST', $P . 'bank-reconciliation/' . $rec4->id . '/add-adjustment', ['kind' => 'bank_charge', 'amount' => 50, 'date' => "$year-12-31", 'account_id' => $acc('631'), 'reviewed' => 1], $mgr);
        $ok($eq($bal('631'), 0), 'a charge dated in the closed year is refused (' . $flash() . ')');
        $call('DELETE', $P . 'bank-reconciliation/' . $rec4->id, [], $mgr);
    }

    // Reversal: with the review and a reason.
    $r = $call('GET', $P . 'year-end-closing/' . $yc->id . '/preview/reverse', [], $mgr, true);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'ap-preview'), 'reversal review renders');
    $call('POST', $P . 'year-end-closing/' . $yc->id . '/reverse', ['reason' => 'Late invoice'], $mgr);
    $ok($in(fn() => $yc->fresh())->status === 'completed', 'reversal without the review tick is refused');
    $call('POST', $P . 'year-end-closing/' . $yc->id . '/reverse', ['reviewed' => 1, 'reason' => 'Late invoice'], $mgr);
    $yc = $in(fn() => $yc->fresh());
    $ok($yc->status === 'reversed' && !$in(fn() => $fy->fresh())->is_closed && !$in(fn() => $dec->fresh())->is_closed, 'reversed: the year and December are open again (' . $flash() . ')');
    $ok($eq($bal('706'), -12000) && $eq($bal('622'), 26000) && $eq($bal('121'), 0) && $eq($bal('130'), 0), 'the closing entry is reversed: the year\'s balances are back, 121 empty');
    $ok($in(fn() => JournalEntry::find($yc->closing_journal_entry_id))->is_reversed, 'the closing entry is marked reversed (never deleted)');
    $ok(str_contains((string)$in(fn() => $dec->fresh())->closing_note, 'Period Manager'), 'December\'s note says who reopened it');
    $call('POST', $P . 'year-end-closing', ['fiscal_year_id' => $fy->id, 'closing_date' => "$year-12-31",
        'retained_earnings_account_id' => $acc('121'), 'income_summary_account_id' => $acc('130')], $mgr);
    $ok($in(fn() => YearEndClosing::count()) === 2, 'a new closing can be made once the first is reversed');

    // ---- Everything balances.
    Ledger::forBusiness($bid, function () use ($ok) {
        $bad = JournalEntry::where('is_posted', true)->get()->filter(fn($e) => abs($e->lines()->sum('debit') - $e->lines()->sum('credit')) > 0.005)->count();
        $ok($bad === 0, 'every posted entry balances');
    });
    foreach (['bank-reconciliation', 'recurring-entries', 'year-end-closing'] as $p) {
        $r = $call('GET', $P . $p, [], $mgr);
        $ok($r->getStatusCode() === 200 && !str_contains($r->getContent(), 'PosInnova'), "$p list opens at the end, no PosInnova wording");
    }
} catch (Throwable $e) {
    $fail++;
    $fails[] = 'exception';
    echo 'EXCEPTION ', $e->getMessage(), ' @ ', $e->getFile(), ':', $e->getLine(), "\n";
} finally {
    Carbon::setTestNow();
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
if ($fails) {
    echo 'Failed: ', implode(' | ', $fails), "\n";
}
exit($fail ? 1 : 0);
