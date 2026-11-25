<?php
/*
 * Head office books, buses as assets, year-end closing: end to end through the real routes, two
 * branches and a head office, from November 2026 to January 2027 (simulated dates), all rolled back.
 * After every step: every book balanced and agreeing with its records, the head office's account of
 * each branch equal to that branch's 1811, the whole company's balance sheet balanced and its result
 * equal to the sum of the books' results.
 */
require __DIR__ . '/_boot.php';

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAccAccount, BusAccAsset, BusAccDocument, BusAccEntry, BusAgency, BusBooking, BusCompany, BusCompanyStaff, BusCompanyStatement,
    BusDeparture, BusDestination, BusDropPoint, BusPayment, BusStaff, BusStatement, BusVehicle};
use Modules\BusManagement\Service\Accounting\{AccReports, AccountingService, Ledger, LedgerSync};
use Modules\BusManagement\Service\BookingService;
use Modules\UserManagement\Entities\User;

$pass = 0; $fail = 0; $fails = [];
$ok = function ($c, $label) use (&$pass, &$fail, &$fails) { if ($c) { $pass++; } else { $fail++; $fails[] = $label; } print(($c ? 'PASS ' : 'FAIL ') . $label . "\n"); };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;
$at = function (string $when) { Carbon::setTestNow(Carbon::parse($when)); };

DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $at('2026-11-02 09:00');
    $rep = app(AccReports::class); $ledger = app(Ledger::class); $sync = app(LedgerSync::class); $svc = app(AccountingService::class);
    $mgr1 = accTestUser('Manager 1'); $mgr2 = accTestUser('Manager 2'); $director = accTestUser('Director'); $hoAcc = accTestUser('HO Accountant'); $regional = accTestUser('Regional');
    $custId = (string)accTestUser('Customer', 'customer')->id;

    $slug = 'e2e-co-' . Str::lower(Str::random(5));
    BusCompany::forceCreate(['name' => 'E2E Express', 'slug' => $slug, 'status' => 'active', 'money_mode' => 'company', 'bill_mode' => 'company',
        'webhook_code' => Str::lower(Str::random(16)), 'turnaround_minutes' => 60]);
    $co = BusCompany::where('slug', $slug)->first();
    foreach ([[$director, 'director'], [$hoAcc, 'accountant'], [$regional, 'regional']] as [$who, $role]) {
        BusCompanyStaff::create(['company_id' => $co->id, 'user_id' => $who->id, 'role' => $role, 'agency_ids' => [], 'active' => true]);
    }
    $mkBranch = function (string $city, User $mgr) use ($co) {
        BusAgency::forceCreate(['name' => 'E2E ' . $city, 'slug' => 'e2e-' . Str::lower($city) . '-' . Str::lower(Str::random(4)), 'origin_city' => $city, 'status' => 'live',
            'webhook_code' => Str::lower(Str::random(16)), 'company_id' => $co->id, 'branch_label' => $city, 'rules' => [], 'commission_counter_pct' => 3, 'commission_app_pct' => 5]);
        $a = BusAgency::where('name', 'E2E ' . $city)->where('company_id', $co->id)->first();
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
        BusDestination::forceCreate(['agency_id' => $a->id, 'city' => 'Yaounde', 'active' => true, 'duration_minutes' => 360]);
        $dest = BusDestination::where('agency_id', $a->id)->first();
        BusVehicle::forceCreate(['agency_id' => $a->id, 'company_id' => $co->id, 'registration' => 'CO ' . strtoupper(Str::random(3)), 'name' => 'Coach ' . $city, 'class' => 'classic', 'status' => 'ready', 'total_seats' => 10]);
        $bus = BusVehicle::where('agency_id', $a->id)->first();
        BusDeparture::forceCreate(['agency_id' => $a->id, 'destination_id' => $dest->id, 'class' => 'classic', 'kind' => 'fixed', 'date' => '2026-11-20', 'time' => '10:00:00',
            'vehicle_id' => $bus->id, 'seat_plan' => ['seats' => array_map('strval', range(1, 10))], 'capacity' => 10, 'status' => 'scheduled']);
        return [$a, $bus, BusDeparture::where('agency_id', $a->id)->first()];
    };
    [$b1, $bus1, $dep1] = $mkBranch('Bamenda', $mgr1);
    [$b2, $bus2, $dep2] = $mkBranch('Buea', $mgr2);

    $call = function (string $method, string $url, array $data = [], ?User $as = null) use ($app, $kernel) {
        $req = Request::create($url, $method, $data, [], [], ['HTTP_ACCEPT' => $method === 'POST' ? 'application/json' : 'text/html']);
        $app['auth']->guard('web')->setUser($as);
        $req->setLaravelSession($app['session']->driver());
        $app['session']->driver()->put('_token', 'tok');
        $req->headers->set('X-CSRF-TOKEN', 'tok');
        $res = $kernel->handle($req);
        if ($res->getStatusCode() >= 500) {
            echo '   500 on ', $url, ': ', $res->exception?->getMessage(), ' @ ', basename((string)$res->exception?->getFile()), ':', $res->exception?->getLine(), "\n";
        }
        return $res;
    };
    $br = '/partner/bus-agency/accounts';
    $ho = '/partner/bus-company/books';
    $post = function (string $base, string $action, array $data, User $as, bool $confirm = true) use ($call) {
        $p = json_decode($call('POST', "$base/preview/$action", $data, $as)->getContent(), true) ?? [];
        if (!$confirm) {
            return [$p, null];
        }
        $r = $call('POST', "$base/do/$action", $data + ['reviewed' => 1], $as);
        return [$p, (json_decode($r->getContent(), true) ?? []) + ['_status' => $r->getStatusCode()]];
    };
    $acc = fn($owner, string $role) => (string)BusAccAccount::where('agency_id', $owner->id)->where('role', $role)->value('id');
    $bal = fn($owner, string $role, array $tags = []) => $ledger->balance(BusAccAccount::find($acc($owner, $role)), null, $tags);
    $liaison = fn($branch) => $ledger->balance($ledger->branchAccount($co, $branch));

    $check = function (string $step) use ($rep, $sync, $ledger, $co, $b1, $b2, $ok, $eq) {
        // What the minute tick does: trips past their time are closed as departed, then the books sync.
        app(BookingService::class)->closePast();
        $sync->sync($co);
        $books = array_filter([$co, $b1, $b2], fn($o) => $ledger->books($o));
        $problems = [];
        $sumResult = 0.0;
        $from = '2026-11-01'; $to = today()->toDateString();
        foreach ($books as $o) {
            $raw = DB::table('bus_acc_lines')->where('agency_id', $o->id)->selectRaw('coalesce(sum(debit),0) d, coalesce(sum(credit),0) c')->first();
            if (!$eq($raw->d, $raw->c)) { $problems[] = 'unbalanced ' . $o->id; }
            foreach ($rep->health($o) as $h) { $problems[] = $h; }
            if (!$rep->balanceSheet($o, $to)['balanced']) { $problems[] = 'balance sheet ' . ($o->name); }
            $sumResult += $rep->incomeStatement($o, $from, $to)['result'];
        }
        $cbs = $rep->combined($co, 'balance', $from, $to);
        $cis = $rep->combined($co, 'income', $from, $to);
        $ctb = $rep->combined($co, 'trial', $from, $to);
        if (!$cbs['balanced']) { $problems[] = 'combined balance sheet'; }
        if (!$ctb['balanced']) { $problems[] = 'combined trial balance'; }
        if (!$eq($cis['result'], $sumResult)) { $problems[] = 'combined result ' . $cis['result'] . ' vs ' . $sumResult; }
        $ok(!$problems, "  books hold after: $step" . ($problems ? ' -> ' . implode(' | ', $problems) : ''));
    };

    // ============================================================ 1. Opening: branch 1, head office, then branch 2 later
    [$p, $res] = $post($br, 'open', ['starts_on' => '2026-11-01', 'balances' => ['bank' => 200000, 'safe' => 50000], 'refunds_from' => 'mtn', 'eride_from' => 'mtn'], $mgr1);
    $ok($res['ok'] ?? false, '1 branch Bamenda opens its books');
    $r = $call('GET', $ho, [], $director);
    $ok($r->getStatusCode() === 302 && str_contains((string)$r->headers->get('Location'), '/books/setup'), '1 head office books not open yet: setup');
    $r = $call('GET', "$ho/setup", [], $director);
    $ok($r->getStatusCode() === 200, '1 head office setup page opens');
    [$p, $res] = $post($ho, 'open', ['starts_on' => '2026-11-01', 'balances' => ['bank' => 1000000], 'loans' => 300000, 'eride_from' => 'bank'], $director);
    $ok(($p['ok'] ?? false) && ($res['ok'] ?? false), '1 head office opens its own books (bank 1 000 000, loan 300 000)');
    $ok(BusAccAccount::where('agency_id', $co->id)->where('role', 'branch')->count() === 2, '1 the head office has an account for each branch');
    $ok(!BusAccAccount::where('agency_id', $co->id)->where('role', 'counter_cash')->exists() && !BusAccAccount::where('agency_id', $co->id)->where('role', 'unearned')->exists(), '1 no counter cash or tickets on head office books');
    $r = $call('GET', $ho, [], $regional);
    $ok($r->getStatusCode() === 403, '1 a regional manager is refused the head office books');
    $r = $call('GET', $ho, [], $mgr1);
    $ok($r->getStatusCode() === 302 && str_contains((string)$r->headers->get('Location'), 'bus-company/login'), '1 a branch manager cannot open the head office books');
    // App and web money collected on the company account, for branch 2 (before its books open).
    $pay = function (BusAgency $a, BusDeparture $d, int $amount, int $revenue) use ($custId) {
        $b = BusBooking::create(['code' => strtoupper(Str::random(8)), 'agency_id' => $a->id, 'departure_id' => $d->id, 'customer_id' => $custId,
            'source' => 'app', 'status' => 'accepted', 'amount' => $amount, 'commission_pct' => 5, 'commission' => $amount * 0.05, 'refund_amount' => 0, 'refund_status' => 'none']);
        $p = BusPayment::create(['booking_id' => $b->id, 'agency_id' => $a->id, 'environment' => 'test', 'external_id' => 'e2e-' . Str::random(10), 'amount' => $amount,
            'status' => 'successful', 'paid_at' => now(), 'raw_response' => ['payment-status' => ['amount' => $amount, 'revenue' => $revenue]]]);
        app(BookingService::class)->settlePaid(BusBooking::find($b->id), $p);
        return $b;
    };
    $pay($b2, $dep2, 8000, 7760);
    $check('opening, and a branch 2 payment before its books');
    $ok($eq($liaison($b2), 8000) && $eq($bal($co, 'fapshi'), 8000 - 240) && $eq($bal($co, 'charges'), 240), '1 company account: 7760 on Fapshi after its 240 fee; 8000 held for branch 2');
    $at('2026-11-03 09:00');
    [$p, $res] = $post($br, 'open', ['starts_on' => '2026-11-03', 'balances' => ['bank' => 90000]], $mgr2);
    $ok(($res['ok'] ?? false) && $eq($bal($b2, 'head_office'), 8000), '1 branch Buea opens later: what the head office holds for it (8000) is taken from the head office books');
    $check('branch 2 opened');

    // ============================================================ 2. A bus as an asset, app money, statements, money with a branch
    [$p] = $post($br, 'asset', ['date' => '2026-11-03', 'vehicle_id' => (string)$bus1->id, 'value' => 36000000, 'years' => 5, 'end_value' => 6000000, 'funded_by' => 'owner'], $mgr1, false);
    $ok(!($p['ok'] ?? true), '2 a branch of a company cannot carry a bus (the head office does)');
    [$p, $res] = $post($ho, 'asset', ['date' => '2026-11-03', 'vehicle_id' => (string)$bus1->id, 'value' => 36000000, 'years' => 5, 'end_value' => 6000000, 'funded_by' => 'owner'], $hoAcc);
    $ok(($res['ok'] ?? false) && collect($p['summary'] ?? [])->contains(fn($x) => $x[0] === 'Wear each month' && str_contains($x[1], '500 000')), '2 head office accountant adds the Bamenda coach: 36M, 5 years, 6M at the end: 500 000 a month');
    [$p] = $post($ho, 'asset', ['date' => '2026-11-03', 'vehicle_id' => (string)$bus1->id, 'value' => 1000, 'years' => 1, 'funded_by' => 'owner'], $director, false);
    $ok(!($p['ok'] ?? true), '2 the same bus cannot be added twice');
    $pay($b1, $dep1, 6000, 5820);
    $check('app payment to the company account for branch 1');
    $ok($eq($bal($b1, 'head_office'), 6000) && $eq($liaison($b1), 6000), '2 branch 1: 6000 held by the head office, on both sides');
    $st = BusStatement::create(['agency_id' => $b1->id, 'month' => '2026-11-01', 'total' => 300, 'due_on' => '2026-12-10', 'status' => 'due']);
    $check('branch statement made');
    $cs = BusCompanyStatement::create(['company_id' => $co->id, 'month' => '2026-11-01', 'total' => 300, 'due_on' => '2026-12-10', 'status' => 'paid', 'paid_at' => now()]);
    $st->update(['company_statement_id' => $cs->id, 'status' => 'paid', 'paid_at' => now()]);
    $check('company statement paid for its branches');
    $ok($eq($bal($b1, 'eride'), 0) && $eq($bal($co, 'eride'), 0) && $eq($liaison($b1), 5700), '2 E-Ride paid by the head office: nothing owed anywhere, branch 1 now holds 5700 at the head office');
    [$p, $res] = $post($ho, 'branchmoney', ['branch_id' => (string)$b1->id, 'direction' => 'from_branch', 'date' => '2026-11-03', 'amount' => 100000,
        'money_account_id' => $acc($co, 'bank'), 'branch_account_id' => $acc($b1, 'bank')], $director);
    $ok(($res['ok'] ?? false) && $eq($bal($b1, 'bank'), 100000) && $eq($bal($co, 'bank'), 1000000 - 300 + 100000) && $eq($liaison($b1), 105700), '2 branch 1 hands 100 000 to the head office: both books posted together');
    $check('money handed in');
    $hob = BusAccDocument::where('agency_id', $co->id)->where('type', 'branch_money')->first();
    [$p, $res] = $post($ho, 'void', ['document' => (string)$hob->id, 'reason' => 'Wrong branch'], $director);
    $ok(($res['ok'] ?? false) && BusAccDocument::where('agency_id', $b1->id)->where('type', 'branch_money')->value('status') === 'void' && $eq($liaison($b1), 5700), '2 voiding it at the head office voids both sides');
    $check('money void');
    [$p] = $post($ho, 'branchmoney', ['branch_id' => (string)$b1->id, 'direction' => 'from_branch', 'date' => '2026-11-03', 'amount' => 100, 'money_account_id' => $acc($co, 'bank'),
        'branch_account_id' => $acc($b2, 'bank')], $director, false);
    $ok(!($p['ok'] ?? true), '2 a branch account of another branch is refused');

    // ============================================================ 3. Bus wear each month, charged to its branch
    $at('2026-12-01 08:00');
    $check('1 December (November wear posted)');
    $ok($eq(-$bal($co, 'bus_wear'), 500000) && $eq($bal($b1, 'depreciation'), 500000) && $eq($liaison($b1), 5700 - 500000), '3 November wear: 2845 at the head office, 6813 at Bamenda, charged through their accounts');
    $profit = collect($rep->profit($b1, '2026-11-01', '2026-11-30', 'bus'))->firstWhere('key', (string)$bus1->id);
    $ok($profit && $eq($profit['other'], 500000), '3 Bamenda profit per bus counts the coach wear');
    $sync->sync($co);
    $ok(BusAccEntry::where('agency_id', $co->id)->where('source_type', 'wear')->count() === 1, '3 wear posted once');

    // ============================================================ 4. Months and the year
    foreach ([[$br, $mgr1], [$br, $mgr2], [$ho, $director]] as [$base, $who]) {
        [$p, $res] = $post($base, 'close', ['month' => '2026-11-01'], $who);
        $ok($res['ok'] ?? false, '4 November closed (' . ($base === $ho ? 'head office' : $who->email) . ')');
    }
    $at('2027-01-01 08:00');
    $check('1 January (December wear)');
    [$p] = $post($ho, 'closeyear', ['year' => 2026], $director, false);
    $ok(!($p['ok'] ?? true) && str_contains(json_encode($p['problems']), 'December'), '4 the year cannot close before December is closed everywhere');
    foreach ([[$br, $mgr1], [$br, $mgr2], [$ho, $director]] as [$base, $who]) {
        $post($base, 'close', ['month' => '2026-12-01'], $who);
    }
    [$p] = $post($br, 'closeyear', ['year' => 2026], $mgr1, false);
    $ok(!($p['ok'] ?? true) && str_contains(json_encode($p['problems']), 'head office'), '4 a branch of a company does not close its own year');
    $is2026 = ['co' => $rep->incomeStatement($co, '2026-01-01', '2026-12-31')['result'], 'b1' => $rep->incomeStatement($b1, '2026-01-01', '2026-12-31')['result'], 'b2' => $rep->incomeStatement($b2, '2026-01-01', '2026-12-31')['result']];
    [$p, $res] = $post($ho, 'closeyear', ['year' => 2026], $director);
    $ok(($p['ok'] ?? false) && ($res['ok'] ?? false) && count($p['summary'] ?? []) >= 3, '4 the director closes 2026 for the head office and both branches (results shown per book)');
    $ok($ledger->yearClosed((string)$co->id, '2026-06-01') && $ledger->yearClosed((string)$b1->id, '2026-06-01') && $ledger->yearClosed((string)$b2->id, '2026-06-01'), '4 2026 locked in all three books');
    $ok($eq($bal($b1, 'result'), $is2026['b1']) && $eq($bal($co, 'result'), $is2026['co']), '4 each book: the 2026 result sits on 1301');
    $ok($eq($rep->incomeStatement($b1, '2026-01-01', '2026-12-31')['result'], $is2026['b1']), '4 the 2026 income statement is unchanged by the closing entry');
    $ok($eq($rep->balanceSheet($b1, '2027-01-01')['result'], 0) && $rep->balanceSheet($b1, '2027-01-01')['balanced'], '4 the 2027 balance sheet starts with no open result, and balances');
    [$p] = $post($ho, 'expense', ['date' => '2026-12-20', 'account_id' => (string)BusAccAccount::where('agency_id', $co->id)->where('code', '6222')->value('id'), 'amount' => 1000, 'paid' => 'now', 'money_account_id' => $acc($co, 'bank')], $director, false);
    $ok(!($p['ok'] ?? true), '4 nothing can be dated in 2026 any more');
    $check('year closed');
    [$p] = $post($ho, 'reopenyear', ['year' => 2026, 'reason' => 'Late invoice from the garage'], $hoAcc, false);
    $ok(!($p['ok'] ?? true), '4 the head office accountant cannot reopen a year');
    [$p, $res] = $post($ho, 'reopenyear', ['year' => 2026, 'reason' => 'Late invoice from the garage'], $director);
    $ok(($res['ok'] ?? false) && !$ledger->yearClosed((string)$b1->id, '2026-06-01') && $eq($bal($b1, 'result'), 0), '4 the director reopens 2026: closing mirrored, 1301 back to zero');
    $check('year reopened');
    [$p, $res] = $post($ho, 'closeyear', ['year' => 2026], $director);
    $ok(($res['ok'] ?? false) && $ledger->yearClosed((string)$b2->id, '2026-06-01'), '4 and closes it again');
    $check('year closed again');

    // ============================================================ 5. The bus is sold
    $at('2027-01-15 10:00');
    $asset = BusAccAsset::where('agency_id', $co->id)->first();
    [$p, $res] = $post($ho, 'dispose', ['asset' => (string)$asset->id, 'date' => '2027-01-15', 'how' => 'sold', 'price' => 34000000, 'money_account_id' => $acc($co, 'bank')], $director);
    $ok(($res['ok'] ?? false) && collect($p['summary'] ?? [])->contains(fn($x) => $x[0] === 'Value left' && str_contains($x[1], '35 000 000')), '5 sold for 34M: value left 35M after two months of wear');
    $ok($eq($bal($co, 'buses'), 0) && $eq($bal($co, 'bus_wear'), 0) && $eq($bal($co, 'disposal_value'), 35000000) && $eq($bal($co, 'disposal_price'), 34000000), '5 the bus is off the books, the 1M loss shows (8120 / 8220)');
    $check('bus sold');
    $at('2027-03-02 08:00');
    $check('March');
    $ok(BusAccEntry::where('agency_id', $co->id)->where('source_type', 'wear')->whereNull('reverses_id')->count() === 2, '5 no wear after the sale');

    // ============================================================ 6. Every head office page opens
    $bad = [];
    foreach (['', '/spending', '/money', '/buses', '/reports', '/reports?tab=profit', '/reports?tab=cash', '/reports?tab=trial', '/reports?tab=balance',
        '/reports?scope=all', '/reports?scope=all&tab=profit', '/reports?scope=all&tab=trial', '/reports?scope=all&tab=balance', '/journal', '/settings',
        '/reconcile?account=' . $acc($co, 'bank'), '/documents/' . $hob->id] as $pg) {
        $r = $call('GET', $ho . $pg . (str_contains($pg, '?') ? '&' : '?') . 'from=2026-11-01', [], $director);
        if ($r->getStatusCode() !== 200) { $bad[] = $pg . ' ' . $r->getStatusCode(); }
    }
    $ok(!$bad, '6 every head office books page opens ' . implode(', ', $bad));
    $r = $call('GET', '/partner/bus-company/accounts?from=2026-11-01', [], $director);
    $ok($r->getStatusCode() === 200, '6 branches side by side opens');
    $r = $call('GET', "$br/buses", [], $mgr1);
    $ok($r->getStatusCode() === 200 || $r->getStatusCode() === 302, '6 the branch Buses page answers');
    foreach (['', '/settings', '/reports?tab=balance'] as $pg) {
        $r = $call('GET', $br . $pg, [], $mgr1);
        if ($r->getStatusCode() !== 200) { $bad[] = 'branch' . $pg; }
    }
    $ok(!$bad, '6 branch pages still open ' . implode(', ', $bad));
} catch (Throwable $e) {
    $fail++;
    echo 'ERROR ', get_class($e), ': ', $e->getMessage(), ' @ ', $e->getFile(), ':', $e->getLine(), "\n";
} finally {
    DB::rollBack();
    Carbon::setTestNow();
}
echo "\n$pass passed, $fail failed\n";
if ($fails) { echo "Failed:\n - " . implode("\n - ", $fails) . "\n"; }
