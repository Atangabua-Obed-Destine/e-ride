<?php
/*
 * Staff pay, budgets and VAT, end to end through the real routes, on a branch without a head office
 * and on a head office, with simulated dates (November and December 2026), all rolled back.
 * After every step the books balance and agree with their records.
 */
require __DIR__ . '/_boot.php';

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAccAccount, BusAccDocument, BusAccEmployee, BusAccPayslip, BusAgency, BusBooking, BusCompany, BusCompanyStaff, BusDeparture, BusDestination, BusStaff, BusVehicle};
use Modules\BusManagement\Lib\CameroonPayroll;
use Modules\BusManagement\Service\Accounting\{AccReports, Ledger, LedgerSync};
use Modules\BusManagement\Service\BookingService;
use Modules\UserManagement\Entities\User;

$pass = 0; $fail = 0; $fails = [];
$ok = function ($c, $label) use (&$pass, &$fail, &$fails) { if ($c) { $pass++; } else { $fail++; $fails[] = $label; } print(($c ? 'PASS ' : 'FAIL ') . $label . "\n"); };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;
$at = fn(string $when) => Carbon::setTestNow(Carbon::parse($when));

DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $at('2026-11-05 09:00');
    $rep = app(AccReports::class); $ledger = app(Ledger::class); $sync = app(LedgerSync::class);

    // ---- The scale itself: the IRPP at the barème's own points.
    $off = collect(CameroonPayroll::checkpoints())->filter(fn($want, $gross) => abs(CameroonPayroll::compute($gross)['irpp'] - $want) > 1);
    $ok($off->isEmpty(), '0 IRPP matches the DGI barème at its 16 checkpoints (within 1 franc)');
    $c = CameroonPayroll::compute(150000);
    $ok($eq($c['cnps'], 6300) && $eq($c['cac'], round($c['irpp'] / 10)) && $eq($c['cfc'], 1500) && $eq($c['crtv'], 1950) && $eq($c['net'], 150000 - $c['withheld']), '0 150 000: CNPS 6 300, CAC a tenth of IRPP, CFC 1%, CRTV 1 950, net = gross - withheld');
    $ok($eq(CameroonPayroll::compute(3000000)['cnps'], 75000), '0 CNPS stops at 75 000 a month');
    $ok($eq(CameroonPayroll::compute(40000)['crtv'], 0) && $eq(CameroonPayroll::compute(40000)['irpp'], 0), '0 below the scale floor: no CRTV, no IRPP');

    $mgr = accTestUser('Pay Manager');
    BusAgency::forceCreate(['name' => 'Pay Test Bus', 'slug' => 'pay-' . Str::lower(Str::random(6)), 'origin_city' => 'Bamenda', 'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => []]);
    $a = BusAgency::where('name', 'Pay Test Bus')->latest('created_at')->first();
    BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);

    $call = function (string $method, string $url, array $data = [], ?User $as = null) use ($app, $kernel) {
        $req = Request::create($url, $method, $data, [], [], ['HTTP_ACCEPT' => $method === 'POST' && str_contains($url, '/do/') || str_contains($url, '/preview/') ? 'application/json' : 'text/html']);
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
    $post = function (string $base, string $action, array $data, User $as, bool $confirm = true) use ($call) {
        $p = json_decode($call('POST', "$base/preview/$action", $data, $as)->getContent(), true) ?? [];
        if (!$confirm) { return [$p, null]; }
        $r = $call('POST', "$base/do/$action", $data + ['reviewed' => 1], $as);
        return [$p, (json_decode($r->getContent(), true) ?? []) + ['_status' => $r->getStatusCode()]];
    };
    $id = fn($owner, string $role) => (string)BusAccAccount::where('agency_id', $owner->id)->where('role', $role)->value('id');
    $bal = fn($owner, string $role) => $ledger->balance(BusAccAccount::find($id($owner, $role)));
    $holds = function ($owner, string $step) use ($rep, $sync, $ok) {
        $sync->sync($owner);
        $tb = $rep->trialBalance($owner, '2026-01-01', today()->toDateString());
        $bs = $rep->balanceSheet($owner, today()->toDateString());
        $h = $rep->health($owner);
        $ok($tb['balanced'] && $bs['balanced'] && !$h, "  books hold after: $step" . ($h ? ' -> ' . implode(' | ', $h) : ''));
    };

    [$p, $res] = $post($br, 'open', ['starts_on' => '2026-11-01', 'balances' => ['bank' => 2000000, 'safe' => 300000]], $mgr);
    $ok($res['ok'] ?? false, '1 books open');

    // ============================================================ Staff and payroll
    foreach ([['Driver One', 'driver', 150000, 'CN-001'], ['Agent One', 'counter', 90000, 'CN-002'], ['Loader One', 'loader', 40000, null]] as [$n, $job, $pay, $cnps]) {
        $r = $call('POST', "$br/staff-pay/people", ['name' => $n, 'job' => $job, 'base_pay' => $pay, 'cnps_number' => $cnps], $mgr);
    }
    $people = BusAccEmployee::where('agency_id', $a->id)->get()->keyBy('name');
    $ok($people->count() === 3, '2 three people on the staff');
    $r = $call('GET', "$br/staff-pay", [], $mgr);
    $ok($r->getStatusCode() === 200, '2 the Staff pay page opens');
    $staff = [];
    foreach ($people as $e) { $staff[(string)$e->id] = ['include' => 1, 'extra' => $e->name === 'Driver One' ? 20000 : 0]; }
    [$p, $res] = $post($br, 'payroll', ['month' => '2026-11', 'date' => '2026-11-05', 'staff' => $staff], $mgr);
    $doc = BusAccDocument::where('agency_id', $a->id)->where('type', 'payroll')->first();
    $ok(($p['ok'] ?? false) && collect($p['effects'] ?? [])->contains(fn($e) => ($e['title'] ?? '') === 'Payslips' && count($e['rows']) === 3), '2 payroll preview shows three payslips');
    $ok(($res['ok'] ?? false) && $doc, '2 payroll of November posted (' . ($doc->number ?? '') . ')');
    $expect = ['Driver One' => CameroonPayroll::compute(170000), 'Agent One' => CameroonPayroll::compute(90000), 'Loader One' => CameroonPayroll::compute(40000)];
    $slips = BusAccPayslip::where('document_id', $doc->id)->get()->keyBy(fn($s) => $s->detail['name']);
    $ok($slips->every(fn($s, $name) => $eq($s->net, $expect[$name]['net']) && $eq($s->irpp, $expect[$name]['irpp']) && $eq($s->employer_cnps, $expect[$name]['employer_cnps'])), '2 each payslip is the rules (the driver with his 20 000 extra)');
    $gross = 170000 + 90000 + 40000;
    $net = collect($expect)->sum('net');
    $cnps = collect($expect)->sum('cnps') + collect($expect)->sum('employer_cnps');
    $state = collect($expect)->sum(fn($x) => $x['irpp'] + $x['cac'] + $x['cfc'] + $x['crtv'] + $x['tdl'] + $x['employer_taxes']);
    $ok($eq($bal($a, 'salaries'), $gross) && $eq($bal($a, 'pay_owed'), $net) && $eq($bal($a, 'cnps'), $cnps) && $eq($bal($a, 'state_tax'), $state),
        "2 posted: 6611 gross $gross, 4221 net, 4311 CNPS $cnps (both shares), 4471 State $state");
    $holds($a, 'payroll');
    [$p] = $post($br, 'payroll', ['month' => '2026-11', 'date' => '2026-11-05', 'staff' => $staff], $mgr, false);
    $ok(!($p['ok'] ?? true) && str_contains(json_encode($p['problems']), 'already posted'), '2 a second November payroll is refused');
    [$p] = $post($br, 'payroll', ['month' => '2026-12', 'date' => '2026-11-05', 'staff' => $staff], $mgr, false);
    $ok(!($p['ok'] ?? true), '2 a payroll for a month not begun is refused');
    $r = $call('GET', "$br/payslips/" . $slips['Driver One']->id, [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), number_format($expect['Driver One']['net'], 0, '.', ' ')), '2 the driver payslip prints with his net pay');
    $r = $call('GET', "$br/documents/" . $doc->id, [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Pay the staff'), '2 the payroll page offers to pay the staff');

    // Paying the staff, voiding it, paying again; the payroll cannot be voided once paid.
    [$p, $res] = $post($br, 'staffpay', ['document' => (string)$doc->id, 'date' => '2026-11-05', 'money_account_id' => $id($a, 'bank')], $mgr);
    $ok(($res['ok'] ?? false) && $eq($bal($a, 'pay_owed'), 0) && $eq($doc->fresh()->owed, 0) && $eq($bal($a, 'bank'), 2000000 - $net), '3 staff paid from the bank: nothing owed to them');
    [$p] = $post($br, 'void', ['document' => (string)$doc->id, 'reason' => 'Wrong month'], $mgr, false);
    $ok(!($p['ok'] ?? true), '3 a paid payroll cannot be voided');
    $spy = BusAccDocument::where('agency_id', $a->id)->where('type', 'staff_pay')->first();
    $post($br, 'void', ['document' => (string)$spy->id, 'reason' => 'Paid from the wrong account'], $mgr);
    $ok($eq($doc->fresh()->owed, $net) && $eq($bal($a, 'pay_owed'), $net), '3 voiding the payment: owed to the staff again');
    [$p] = $post($br, 'staffpay', ['document' => (string)$doc->id, 'date' => '2026-11-05', 'money_account_id' => $id($a, 'counter_cash')], $mgr, false);
    $ok(!($p['ok'] ?? true), '3 staff are not paid from an agent cash');
    $post($br, 'staffpay', ['document' => (string)$doc->id, 'date' => '2026-11-05', 'money_account_id' => $id($a, 'safe')], $mgr);
    $ok($eq($bal($a, 'pay_owed'), 0), '3 paid again from the safe');
    $holds($a, 'staff paid');

    // Paying CNPS and the State.
    [$p] = $post($br, 'remit', ['to' => 'cnps', 'date' => '2026-11-05', 'amount' => $cnps + 1000, 'money_account_id' => $id($a, 'bank')], $mgr, false);
    $ok(!($p['ok'] ?? true), '4 paying CNPS more than owed is refused');
    [$p, $res] = $post($br, 'remit', ['to' => 'cnps', 'date' => '2026-11-05', 'amount' => $cnps, 'money_account_id' => $id($a, 'bank'), 'reference' => 'CNPS-11'], $mgr);
    $ok(($res['ok'] ?? false) && $eq($bal($a, 'cnps'), 0), '4 CNPS paid in full');
    $post($br, 'remit', ['to' => 'state', 'date' => '2026-11-05', 'amount' => 5000, 'money_account_id' => $id($a, 'bank'), 'reference' => 'DGI-11'], $mgr);
    $ok($eq($bal($a, 'state_tax'), $state - 5000), '4 the State paid in part: the rest still owed');
    $holds($a, 'remittances');

    // ============================================================ Budget
    $fuel = $id($a, 'fuel'); $sal = $id($a, 'salaries');
    $r = $call('POST', "$br/budget", ['year' => 2026, 'amounts' => [$fuel => array_fill(1, 12, 100000), $sal => array_fill(1, 12, 300000)]], $mgr);
    $ok(DB::table('bus_acc_budgets')->where('agency_id', $a->id)->where('year', 2026)->count() === 24, '5 budget saved: fuel and salaries, 12 months each');
    $r = $call('GET', "$br/budget?year=2026", [], $mgr);
    $ok($r->getStatusCode() === 200, '5 the budget page opens');
    $post($br, 'expense', ['date' => '2026-11-05', 'account_id' => $fuel, 'amount' => 120000, 'paid' => 'now', 'money_account_id' => $id($a, 'bank')], $mgr);
    $bva = $rep->budgetVsActual($a, '2026-11-01', '2026-11-30');
    $fuelRow = collect($bva['rows']['spending'])->first(fn($x) => (string)$x['account']->id === $fuel);
    $salRow = collect($bva['rows']['spending'])->first(fn($x) => (string)$x['account']->id === $sal);
    $ok($fuelRow && $fuelRow['flag'] && $eq($fuelRow['diff'], 20000) && $fuelRow['used'] == 120, '5 fuel 120 000 against 100 000: over budget by 20 000 (120%)');
    $ok($salRow && !$salRow['flag'] && $eq($salRow['actual'], $gross), '5 salaries within budget');
    $r = $call('GET', "$br/reports?tab=budget&from=2026-11-01&to=2026-11-30", [], $mgr);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Over budget'), '5 the budget report shows it');

    // ============================================================ VAT
    $r = $call('POST', "$br/settings", ['eride_from' => 'mtn', 'refunds_from' => 'mtn', 'vat_registered' => 1, 'vat_on_tickets' => 1, 'vat_rate' => 19.25], $mgr);
    $books = $ledger->books($a);
    $ok($books->setting('vat_registered') && $books->setting('vat_on_tickets'), '6 the branch is registered for VAT, tickets include it');
    $post($br, 'expense', ['date' => '2026-11-05', 'account_id' => $id($a, 'other_expense'), 'amount' => 11925, 'vat' => 1925, 'paid' => 'now', 'money_account_id' => $id($a, 'bank')], $mgr);
    $ok($eq($bal($a, 'vat_in'), 1925), '6 an invoice with 1 925 VAT: the VAT is to recover, not a cost');
    $post($br, 'income', ['date' => '2026-11-05', 'account_id' => (string)BusAccAccount::where('agency_id', $a->id)->where('code', '7071')->value('id'), 'amount' => 11925, 'vat_included' => 1, 'money_account_id' => $id($a, 'safe')], $mgr);
    $ok($eq($bal($a, 'vat_out'), 1925), '6 parcel income of 11 925 with VAT: 1 925 VAT collected');
    // A counter ticket of 11 925 on a trip that leaves.
    $dest = BusDestination::forceCreate(['agency_id' => $a->id, 'city' => 'Douala', 'active' => true, 'duration_minutes' => 420]);
    $dest = BusDestination::where('agency_id', $a->id)->first();
    $bus = BusVehicle::forceCreate(['agency_id' => $a->id, 'registration' => 'VAT ' . strtoupper(Str::random(3)), 'name' => 'Coach', 'class' => 'classic', 'status' => 'ready', 'total_seats' => 10]);
    $bus = BusVehicle::where('agency_id', $a->id)->first();
    BusDeparture::forceCreate(['agency_id' => $a->id, 'destination_id' => $dest->id, 'class' => 'classic', 'kind' => 'fixed', 'date' => '2026-11-06', 'time' => '08:00:00', 'vehicle_id' => $bus->id,
        'seat_plan' => ['seats' => ['1', '2']], 'capacity' => 2, 'status' => 'scheduled']);
    $dep = BusDeparture::where('agency_id', $a->id)->first();
    BusBooking::create(['code' => strtoupper(Str::random(8)), 'agency_id' => $a->id, 'departure_id' => $dep->id, 'source' => 'counter', 'status' => 'paid', 'payment_method' => 'cash',
        'paid_at' => now(), 'staff_user_id' => $mgr->id, 'amount' => 11925, 'commission_pct' => 0, 'commission' => 0, 'refund_amount' => 0, 'refund_status' => 'none']);
    $sync->sync($a);
    $ok($eq($ledger->balance(BusAccAccount::find($id($a, 'unearned')), null, ['departure_id' => (string)$dep->id]), 11925), '6 the ticket is held at its full price before the trip');
    $at('2026-11-07 09:00');
    app(BookingService::class)->closePast();
    $sync->sync($a);
    $ok($eq($bal($a, 'sales_counter'), 10000) && $eq($bal($a, 'vat_out'), 3850), '6 the bus left: 10 000 of sales and 1 925 more VAT collected (3 850)');
    $holds($a, 'VAT on sales and purchases');
    [$p] = $post($br, 'vatreturn', ['month' => '2026-11', 'date' => '2026-11-07'], $mgr, false);
    $ok(!($p['ok'] ?? true), '6 November VAT return cannot be made before November is over');
    $at('2026-12-02 09:00');
    [$p, $res] = $post($br, 'vatreturn', ['month' => '2026-11', 'date' => '2026-12-02'], $mgr);
    $ok(($res['ok'] ?? false) && $eq($bal($a, 'vat_due'), 1925) && $eq($bal($a, 'vat_out'), 0) && $eq($bal($a, 'vat_in'), 0), '6 November return: 3 850 collected less 1 925 to recover = 1 925 to pay');
    [$p] = $post($br, 'vatreturn', ['month' => '2026-11', 'date' => '2026-12-02'], $mgr, false);
    $ok(!($p['ok'] ?? true), '6 the same month cannot be returned twice');
    $post($br, 'remit', ['to' => 'vat', 'date' => '2026-12-02', 'amount' => 1925, 'money_account_id' => $id($a, 'bank'), 'reference' => 'TVA-11'], $mgr);
    $ok($eq($bal($a, 'vat_due'), 0), '6 VAT paid');
    $holds($a, 'VAT return and payment');

    // ============================================================ A head office pays its own staff
    $dir = accTestUser('Pay Director');
    $slug = 'pay-co-' . Str::lower(Str::random(5));
    BusCompany::forceCreate(['name' => 'Pay Co', 'slug' => $slug, 'status' => 'active', 'money_mode' => 'branch', 'bill_mode' => 'branch', 'webhook_code' => Str::lower(Str::random(16)), 'turnaround_minutes' => 60]);
    $co = BusCompany::where('slug', $slug)->first();
    BusCompanyStaff::create(['company_id' => $co->id, 'user_id' => $dir->id, 'role' => 'director', 'agency_ids' => [], 'active' => true]);
    $ho = '/partner/bus-company/books';
    $post($ho, 'open', ['starts_on' => '2026-12-01', 'balances' => ['bank' => 1000000]], $dir);
    $call('POST', "$ho/staff-pay/people", ['name' => 'HO Clerk', 'job' => 'manager', 'base_pay' => 250000], $dir);
    $clerk = BusAccEmployee::where('agency_id', $co->id)->first();
    [$p, $res] = $post($ho, 'payroll', ['month' => '2026-12', 'date' => '2026-12-02', 'staff' => [(string)$clerk->id => ['include' => 1]]], $dir);
    $ok(($res['ok'] ?? false) && $eq($bal($co, 'salaries'), 250000) && $eq($bal($co, 'pay_owed'), CameroonPayroll::compute(250000)['net']), '7 the head office runs its own payroll');
    $r = $call('GET', "$ho/staff-pay", [], $dir);
    $ok($r->getStatusCode() === 200, '7 head office Staff pay page opens');
    $holds($co, 'head office payroll');
    $bad = [];
    foreach (['/staff-pay', '/budget', '/reports?tab=budget', '/settings', '/spending', '/money'] as $pg) {
        $r = $call('GET', $br . $pg, [], $mgr);
        if ($r->getStatusCode() !== 200) { $bad[] = $pg . ' ' . $r->getStatusCode(); }
    }
    $ok(!$bad, '8 every page opens ' . implode(', ', $bad));
} catch (Throwable $e) {
    $fail++;
    echo 'ERROR ', get_class($e), ': ', $e->getMessage(), ' @ ', $e->getFile(), ':', $e->getLine(), "\n";
} finally {
    DB::rollBack();
    Carbon::setTestNow();
}
echo "\n$pass passed, $fail failed\n";
if ($fails) { echo "Failed:\n - " . implode("\n - ", $fails) . "\n"; }
