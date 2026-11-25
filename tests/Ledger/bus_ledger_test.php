<?php
/*
 * The accounting module (App\Ledger: paxhitestbed's module as ported to posinnova) on a bus
 * branch, end to end through the real routes (HTTP kernel, middleware, controllers), all rolled
 * back. Opens the books from the branch portal, sets them up through the review, posts ticket
 * money from the bus records, a trip leaving and running again, a manual journal entry, then
 * opens every screen of the stage, and checks a second branch can never see the first's books.
 *
 * Run: php tests/Ledger/bus_ledger_test.php (also on production: sudo -u www-data php8.2 ...).
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Models\JournalEntryLine;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PostingRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAgency, BusDeparture, BusDestination, BusDropPoint, BusMoneyMove, BusStaff, BusVehicle};
use Modules\UserManagement\Entities\User;

$pass = 0; $fail = 0; $fails = [];
$ok = function ($c, $label) use (&$pass, &$fail, &$fails) { if ($c) { $pass++; } else { $fail++; $fails[] = $label; } print(($c ? 'PASS ' : 'FAIL ') . $label . "\n"); };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;

DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    \App\Ledger\LedgerObserver::forget();
    $mgrA = accTestUser('Ledger Manager A'); $agentA = accTestUser('Ledger Agent A'); $mgrB = accTestUser('Ledger Manager B'); $agentOnly = $agentA;

    $mk = function (string $name, string $city, User $mgr, ?User $agent) {
        $id = (string)Str::uuid();
        BusAgency::forceCreate(['id' => $id, 'name' => $name, 'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)), 'origin_city' => $city,
            'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => [], 'commission_counter_pct' => 3, 'commission_app_pct' => 5]);
        $a = BusAgency::where("name", $name)->first();
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
        if ($agent) {
            BusStaff::create(['agency_id' => $a->id, 'user_id' => $agent->id, 'role' => 'counter', 'active' => true]);
        }
        BusDestination::forceCreate(['agency_id' => $a->id, 'city' => 'Yaounde', 'active' => true, 'duration_minutes' => 360]);
        $dest = BusDestination::where('agency_id', $a->id)->first();
        BusDropPoint::forceCreate(['destination_id' => $dest->id, 'name' => 'Main office', 'fare_classic' => 5000, 'active' => true, 'sort' => 1]);
        BusVehicle::forceCreate(['agency_id' => $a->id, 'registration' => 'LDG ' . strtoupper(Str::random(3)), 'name' => 'Coach', 'class' => 'classic', 'status' => 'ready', 'total_seats' => 10]);
        $bus = BusVehicle::where('agency_id', $a->id)->first();
        BusDeparture::forceCreate(['agency_id' => $a->id, 'destination_id' => $dest->id, 'class' => 'classic', 'kind' => 'fixed',
            'date' => today()->addDay()->toDateString(), 'time' => '10:00:00', 'vehicle_id' => $bus->id,
            'seat_plan' => ['seats' => array_map('strval', range(1, 10))], 'capacity' => 10, 'status' => 'scheduled']);
        return [$a, BusDeparture::where('agency_id', $a->id)->first(), $bus];
    };
    [$A, $depA, $busA] = $mk('Ledger Branch Bamenda', 'Bamenda', $mgrA, $agentA);
    [$B, $depB] = $mk('Ledger Branch Buea', 'Buea', $mgrB, null);

    // ---- The HTTP client: a request through the kernel as a given user (session kept between calls).
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

    // ---- 1. Open the books from the branch portal, then set them up through the review.
    $r = $call('GET', '/partner/bus-agency/accounting', [], $mgrA);
    $ok($r->getStatusCode() === 302 && str_ends_with($to($r), '/partner/accounting'), 'branch portal opens Accounting (' . $r->getStatusCode() . ' ' . $to($r) . ')');
    $bidA = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $A->id)->value('id');
    $ok($bidA > 0, 'the branch has a set of books in ledger_businesses');
    $r = $call('GET', '/partner/accounting', [], $mgrA);
    if ($r->getStatusCode() === 302 && !str_contains($to($r), '/setup')) {
        $r = $call('GET', parse_url($to($r), PHP_URL_PATH), [], $mgrA);
    }
    $ok($r->getStatusCode() === 302 && str_contains($to($r), '/partner/accounting/setup'), 'books not set up yet: sent to Setup (' . $to($r) . ')');
    $r = $call('GET', '/partner/accounting/setup', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'review'), 'Setup page opens');
    $r = $call('GET', '/partner/accounting/setup?preview=1', [], $mgrA, true);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'ap-preview'), 'Setup preview (the review dialog) renders');
    $r = $call('POST', '/partner/accounting/setup', ['start' => 'today'], $mgrA);
    $ok(!Ledger::isSetUp($bidA), 'setup without the review tick is refused');
    $r = $call('POST', '/partner/accounting/setup', ['start' => 'today', 'reviewed' => 1], $mgrA);
    $ok($r->getStatusCode() === 302 && Ledger::isSetUp($bidA), 'setup with the review tick creates the books (' . $r->getStatusCode() . ' ' . $to($r) . ')');

    Ledger::forBusiness($bidA, function () use ($ok) {
        $codes = ChartOfAccount::pluck('account_code')->all();
        $ok(count($codes) > 150, 'SYSCOHADA chart created (' . count($codes) . ' accounts)');
        $ok(!array_diff(['477', '706', '709', '4198', '5521', '5522', '5523', '5712', '631', '632', '401', '1811', '4711'], $codes), 'bus accounts are in the chart');
        $ok(\App\Ledger\Models\FiscalYear::count() === 1 && \App\Ledger\Models\AccountingPeriod::count() === 12, 'this year and its 12 months exist');
        $ok(\App\Ledger\Services\DefaultAccount::id('payment_method', 'cash') === ChartOfAccount::where('account_code', '5712')->value('id'), 'counter cash maps to 5712 (agent tills)');
    });

    // ---- 2. Ticket money from the bus records.
    $acc = fn(string $code) => (int)Ledger::forBusiness($bidA, fn() => ChartOfAccount::where('account_code', $code)->value('id'));
    $bal = fn(string $code, ?string $dep = null) => round((float)Ledger::forBusiness($bidA, fn() => JournalEntryLine::where('account_id', ChartOfAccount::where('account_code', $code)->value('id'))
        ->whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->when($dep, fn($q) => $q->where('departure_id', $dep))->selectRaw('COALESCE(SUM(debit - credit), 0) as b')->value('b')), 2);
    $move = function (array $f) use ($A, $depA) {
        $m = BusMoneyMove::create($f + ['agency_id' => $A->id, 'booking_id' => (string)Str::uuid(), 'departure_id' => (string)$depA->id, 'channel' => 'counter', 'at' => now()]);
        LedgerSync::flush();
        return $m;
    };
    $m1 = $move(['kind' => 'sale', 'method' => 'cash', 'amount' => 10000, 'staff_user_id' => $agentA->id]);
    $ok($eq($bal('5712'), 10000) && $eq($bal('477', (string)$depA->id), -10000), 'counter cash sale: Dr 5712 till / Cr 477 held for the trip');
    $line = Ledger::forBusiness($bidA, fn() => JournalEntryLine::where('account_id', ChartOfAccount::where('account_code', '5712')->value('id'))->first());
    $ok($line && (string)$line->agent_id === (string)$agentA->id && (string)$line->departure_id === (string)$depA->id && $line->channel === 'counter' && (string)$line->vehicle_id === (string)$busA->id,
        'the till line carries the agent, the trip, the bus and the channel');
    $map = Ledger::forBusiness($bidA, fn() => \App\Ledger\Models\TransactionMapping::where('transaction_type', PostingRules::MOVE)->where('transaction_id', (string)$m1->id)->first());
    $ok($map && $map->status === 'posted' && $map->journal_entry_id, 'the money move has its mapping (posted)');
    $entry = $map ? Ledger::forBusiness($bidA, fn() => JournalEntry::find($map->journal_entry_id)) : null;
    $ok($entry && $entry->is_system_generated && $entry->is_posted && $entry->reference_type === PostingRules::MOVE && (int)$entry->reference_id === (int)$map->id, 'its entry is a posted system entry referring to the mapping');

    $move(['kind' => 'sale', 'method' => 'momo', 'amount' => 5000, 'staff_user_id' => $agentA->id]);
    $ok($eq($bal('5521'), 5000) && $eq($bal('477', (string)$depA->id), -15000), 'MTN sale: Dr 5521 / Cr 477');
    $move(['kind' => 'refund_owed', 'method' => null, 'amount' => 5000]);
    $ok($eq($bal('4198'), -5000) && $eq($bal('477', (string)$depA->id), -10000), 'refund owed before the trip: Dr 477 / Cr 4198');
    $move(['kind' => 'refund_sent', 'method' => null, 'amount' => 5000]);
    $ok($eq($bal('4198'), 0) && $eq($bal('5521'), 0), 'refund sent from MTN (the default): Dr 4198 / Cr 5521');

    // ---- 3. The trip leaves: what was held becomes sales; it runs again: reversed; leaves again: sales again.
    $depA->update(['status' => 'departed', 'departed_at' => now()->addSecond()]);
    LedgerSync::flush();
    $ok($eq($bal('477', (string)$depA->id), 0) && $eq($bal('706'), -10000), 'trip left: Dr 477 / Cr 706 for what was held (10 000)');
    $move(['kind' => 'cash_refund', 'method' => 'cash', 'amount' => 2000, 'staff_user_id' => $agentA->id, 'at' => now()->addSeconds(5)]);
    $ok($eq($bal('709'), 2000) && $eq($bal('5712'), 8000), 'refund after the trip left: Dr 709 / Cr 5712 (not 477)');
    $depA->update(['status' => 'scheduled', 'departed_at' => null]);
    LedgerSync::flush();
    $ok($eq($bal('706'), 0) && $eq($bal('477', (string)$depA->id), -8000), 'trip runs again: the sale is reversed and the later refund is held again (477 = 8 000)');
    $ok($eq($bal('709'), 0), 'and the refund after the trip moved back to 477');
    $depA->update(['status' => 'departed', 'departed_at' => now()->addMinute()]);
    LedgerSync::flush();
    $ok($eq($bal('477', (string)$depA->id), 0) && $eq($bal('706'), -8000), 'trip leaves again: 8 000 of sales');

    // ---- 4. Everything balances; the sweep finds nothing left to do.
    Ledger::forBusiness($bidA, function () use ($ok) {
        $bad = JournalEntry::where('is_posted', true)->get()->filter(fn($e) => abs($e->lines()->sum('debit') - $e->lines()->sum('credit')) > 0.005)->count();
        $ok($bad === 0, 'every posted entry balances');
        $tb = JournalEntryLine::whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->selectRaw('SUM(debit) d, SUM(credit) c')->first();
        $ok(abs($tb->d - $tb->c) < 0.01, 'trial balance: debits = credits');
        $stats = (new LedgerSync())->sweep();
        $ok(empty($stats['posted'] ?? 0) && empty($stats['failed'] ?? 0) && empty($stats['reposted'] ?? 0), 'sweep finds nothing to post again (' . json_encode($stats) . ')');
    });

    // ---- 5. A manual journal entry: draft, review, post (paxhitestbed's flow).
    $r = $call('GET', '/partner/accounting/journal-entries/create', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'new journal entry form opens');
    $r = $call('POST', '/partner/accounting/journal-entries', [
        'entry_date' => today()->toDateString(), 'journal_type' => 'opening', 'description' => 'Opening cash in the safe',
        'fiscal_year_id' => Ledger::forBusiness($bidA, fn() => \App\Ledger\Models\FiscalYear::value('id')),
        'lines' => [['account_id' => $acc('571'), 'debit' => 50000, 'credit' => 0, 'description' => 'Safe'],
            ['account_id' => $acc('4711'), 'debit' => 0, 'credit' => 50000, 'description' => 'Opening']],
    ], $mgrA);
    $draft = Ledger::forBusiness($bidA, fn() => JournalEntry::where('description', 'Opening cash in the safe')->first());
    $ok($draft && !$draft->is_posted, 'journal entry saved as a draft (' . $r->getStatusCode() . ')');
    if ($draft) {
        $r = $call('GET', "/partner/accounting/journal-entries/{$draft->id}/preview/post", [], $mgrA, true);
        $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'ap-preview'), 'post preview (review dialog) renders');
        $call('POST', "/partner/accounting/journal-entries/{$draft->id}/post", [], $mgrA);
        $ok(!Ledger::forBusiness($bidA, fn() => JournalEntry::find($draft->id))->is_posted, 'posting without the review tick is refused');
        $call('POST', "/partner/accounting/journal-entries/{$draft->id}/post", ['reviewed' => 1], $mgrA);
        $ok(Ledger::forBusiness($bidA, fn() => JournalEntry::find($draft->id))->is_posted && $eq($bal('571'), 50000), 'posted with the review tick: 571 = 50 000');
    }

    // ---- 6. Every screen of the stage opens.
    $pages = ['chart-of-accounts', 'fiscal-years', 'journal-entries', 'general-ledger', 'general-ledger/trial-balance', 'general-ledger/balance-sheet',
        'general-ledger/income-statement', 'mappings/settings', 'cash-accounts', 'mappings/transactions', 'general-ledger/account/' . $acc('5712')];
    foreach ($pages as $p) {
        $r = $call('GET', '/partner/accounting/' . $p, [], $mgrA);
        $ok($r->getStatusCode() === 200, "page $p opens (" . $r->getStatusCode() . ')');
    }
    $html = $call('GET', '/partner/accounting/general-ledger/trial-balance', [], $mgrA)->getContent();
    $ok(str_contains($html, 'Ledger Branch Bamenda') && str_contains($html, 'ledger-nav'), 'the frame shows the branch name and the accounting menu');

    // ---- 7. Walls: another branch's manager, a counter agent, no session.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrB);
    $r = $call('GET', '/partner/accounting/journal-entries', [], $mgrB);
    $bidB = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $B->id)->value('id');
    $ok($bidB && $bidB !== $bidA, 'branch B opens its own books');
    $r = $call('GET', '/partner/accounting/journal-entries/' . ($draft?->id ?? 0), [], $mgrB);
    $ok(in_array($r->getStatusCode(), [403, 404, 302], true), "branch B cannot open branch A's entry (" . $r->getStatusCode() . ')');
    $r = $call('GET', '/partner/accounting/general-ledger/account/' . $acc('5712'), [], $mgrB);
    $ok(!str_contains($r->getContent(), '10 000') && !str_contains($r->getContent(), '10,000'), "branch B's ledger shows nothing of branch A");
    $s2 = $app['session']->driver();
    $s2->forget('ledger.owner');
    $r = $call('GET', '/partner/bus-agency/accounting', [], $agentA);
    $r2 = $call('GET', '/partner/accounting/journal-entries', [], $agentA);
    $ok($r2->getStatusCode() === 302 && !str_contains((string)$r2->getContent(), 'Opening cash'), 'a counter agent cannot open the books (' . $r2->getStatusCode() . ')');
    // an owner-only right: the accountant role cannot reopen a month
    \App\Ledger\ERide\Books::use(['business_id' => $bidA, 'role' => 'accountant', 'name' => 'x', 'portal' => 'bus-partner', 'type' => 'bus_agency', 'owner' => $A]);
    $ok(\App\Ledger\ERide\Books::allows('journal-entry-post') === true && \App\Ledger\ERide\Books::allows('accounting-period-reopen') === false, 'accountant: posts, but cannot reopen a month');
    $ok(\App\Ledger\ERide\Books::allows('some-other-ability') === null, 'the Gate hook leaves non-accounting abilities alone');
} catch (Throwable $e) {
    $fail++;
    $fails[] = 'exception';
    echo 'EXCEPTION ', $e->getMessage(), ' @ ', $e->getFile(), ':', $e->getLine(), "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
if ($fails) {
    echo 'Failed: ', implode(' | ', $fails), "\n";
}
exit($fail ? 1 : 0);
