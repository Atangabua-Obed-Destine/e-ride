<?php
/*
 * Bus branch accounting, end to end through the real routes (HTTP kernel, middleware, controllers),
 * two branches, about one month each, all rolled back. After every step the books are checked
 * against the raw ledger: every entry balanced, trial balance, balance sheet, income statement =
 * balance sheet result, cash flow agrees, profit by bus = result, nothing pending, closed months
 * untouched, and each branch only ever holds its own lines. Then every page is opened.
 */
require __DIR__ . '/_boot.php';

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAccAccount, BusAccDocument, BusAgency, BusBooking, BusDeparture, BusDestination, BusDropPoint, BusStaff, BusStatement, BusVehicle};
use Modules\BusManagement\Service\Accounting\{AccReports, AccountingService, Ledger, LedgerSync};
use Modules\UserManagement\Entities\User;

$pass = 0; $fail = 0; $fails = [];
$ok = function ($c, $label) use (&$pass, &$fail, &$fails) { if ($c) { $pass++; } else { $fail++; $fails[] = $label; } print(($c ? 'PASS ' : 'FAIL ') . $label . "\n"); };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;

DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $rep = app(AccReports::class); $ledger = app(Ledger::class); $sync = app(LedgerSync::class); $svc = app(AccountingService::class);
    $mgrA = accTestUser('Manager A'); $agentA = accTestUser('Agent A'); $mgrB = accTestUser('Manager B'); $accB = accTestUser('Accountant B');
    $director = accTestUser('Director'); $regionalUser = accTestUser('Regional');
    $custId = (string)accTestUser('Customer', 'customer')->id;
    $coSlug = 'e2e-co-' . Str::lower(Str::random(6));
    Modules\BusManagement\Entities\BusCompany::forceCreate(['name' => 'E2E Company', 'slug' => $coSlug, 'status' => 'active', 'money_mode' => 'branch', 'bill_mode' => 'branch',
        'webhook_code' => Str::lower(Str::random(16)), 'turnaround_minutes' => 60]);
    $companyId = (string)Modules\BusManagement\Entities\BusCompany::where('slug', $coSlug)->value('id');
    foreach ([[$director, 'director'], [$regionalUser, 'regional']] as [$who, $role]) {
        Modules\BusManagement\Entities\BusCompanyStaff::create(['company_id' => $companyId, 'user_id' => $who->id, 'role' => $role, 'agency_ids' => [], 'active' => true]);
    }

    // ---- Two branches with a route, a bus and departures.
    $mk = function (string $name, string $city, $mgr, $second, string $secondRole) use ($companyId) {
        $a = BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => $name, 'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)), 'origin_city' => $city,
            'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'company_id' => $companyId, 'branch_label' => $city, 'rules' => [], 'commission_counter_pct' => 3, 'commission_app_pct' => 5]);
        $a = BusAgency::where('name', $name)->first();
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $second->id, 'role' => $secondRole, 'active' => true]);
        $dest = BusDestination::forceCreate(['agency_id' => $a->id, 'city' => 'Yaounde', 'active' => true, 'duration_minutes' => 360]);
        $dest = BusDestination::where('agency_id', $a->id)->first();
        BusDropPoint::forceCreate(['destination_id' => $dest->id, 'name' => 'Main office', 'fare_classic' => 5000, 'active' => true, 'sort' => 1]);
        $bus = BusVehicle::forceCreate(['agency_id' => $a->id, 'company_id' => $companyId, 'registration' => 'E2E ' . strtoupper(Str::random(3)), 'name' => 'Coach', 'class' => 'classic', 'status' => 'ready', 'total_seats' => 10]);
        $bus = BusVehicle::where('agency_id', $a->id)->first();
        return [$a, $dest, $bus];
    };
    [$A, $destA, $busA] = $mk('E2E Branch Bamenda', 'Bamenda', $mgrA, $agentA, 'counter');
    [$B, $destB, $busB] = $mk('E2E Branch Buea', 'Buea', $mgrB, $accB, 'accountant');
    $dep = fn(BusAgency $a, $dest, $bus, int $days) => BusDeparture::forceCreate(['agency_id' => $a->id, 'destination_id' => $dest->id, 'class' => 'classic', 'kind' => 'fixed',
        'date' => today()->addDays($days)->toDateString(), 'time' => '10:00:00', 'vehicle_id' => $bus->id, 'seat_plan' => ['seats' => array_map('strval', range(1, 10))], 'capacity' => 10, 'status' => 'scheduled']);
    $depA1 = $dep($A, $destA, $busA, 1); $depA1 = BusDeparture::where('agency_id', $A->id)->first();
    $dropA = (string)BusDropPoint::where('destination_id', $destA->id)->value('id');

    // ---- The HTTP client: a request through the kernel as a given user.
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
    $acc = '/partner/bus-agency/accounts';
    // Preview, check it is clean, then confirm (reviewed=1). Returns [preview, result].
    $post = function (string $action, array $data, User $as, bool $expectOk = true) use ($call, $acc) {
        $p = json_decode($call('POST', "$acc/preview/$action", $data, $as)->getContent(), true) ?? [];
        if (!$expectOk) {
            return [$p, null];
        }
        $r = $call('POST', "$acc/do/$action", $data + ['reviewed' => 1], $as);
        return [$p, json_decode($r->getContent(), true) + ['_status' => $r->getStatusCode()]];
    };
    $role = fn(BusAgency $a, string $r) => (string)BusAccAccount::where('agency_id', $a->id)->where('role', $r)->value('id');
    $code = fn(BusAgency $a, string $c) => (string)BusAccAccount::where('agency_id', $a->id)->where('code', $c)->value('id');

    // ---- The checks after every step.
    $checkBooks = function (BusAgency $a, string $step) use ($rep, $sync, $ledger, $ok, $eq) {
        $sync->sync($a);
        $books = $ledger->books($a);
        if (!$books) { return; }
        $from = $books->starts_on->toDateString(); $to = today()->toDateString();
        $raw = DB::table('bus_acc_lines')->where('agency_id', $a->id)->selectRaw('coalesce(sum(debit),0) d, coalesce(sum(credit),0) c, count(*) n')->first();
        $unbalanced = DB::table('bus_acc_lines')->where('agency_id', $a->id)->groupBy('entry_id')->havingRaw('abs(sum(debit) - sum(credit)) > 0.004')->select('entry_id')->get()->count();
        $foreign = DB::table('bus_acc_lines as l')->join('bus_acc_accounts as x', 'x.id', '=', 'l.account_id')->where('l.agency_id', $a->id)->whereColumn('x.agency_id', '!=', 'l.agency_id')->count();
        $totals = DB::table('bus_acc_entries as e')->where('e.agency_id', $a->id)
            ->whereRaw('abs(e.total - (select coalesce(sum(debit),0) from bus_acc_lines l where l.entry_id = e.id)) > 0.004')->count();
        $tb = $rep->trialBalance($a, $from, $to);
        $bs = $rep->balanceSheet($a, $to);
        $is = $rep->incomeStatement($a, $from, $to);
        $cf = $rep->cashFlow($a, $from, $to);
        $profit = collect($rep->profit($a, $from, $to, 'bus'))->sum('profit');
        $ov = $rep->overview($a);
        $rawMoney = (float)DB::table('bus_acc_lines as l')->join('bus_acc_accounts as x', 'x.id', '=', 'l.account_id')->where('l.agency_id', $a->id)
            ->where(fn($q) => $q->where('x.money', true)->orWhere('x.role', 'head_office'))->selectRaw('coalesce(sum(l.debit - l.credit),0) b')->value('b');
        $lateInClosed = DB::table('bus_acc_entries as e')->join('bus_acc_months as m', function ($j) {
            $j->on('m.agency_id', '=', 'e.agency_id')->whereNotNull('m.closed_at')->whereRaw("date_format(e.date, '%Y-%m-01') = m.month")->whereColumn('e.created_at', '>', 'm.closed_at');
        })->where('e.agency_id', $a->id)->count();
        $all = $eq($raw->d, $raw->c) && $unbalanced === 0 && $foreign === 0 && $totals === 0 && $tb['balanced'] && $bs['balanced'] && $eq($is['result'], $bs['result'])
            && $cf['agrees'] && $eq($cf['closing'], $rawMoney) && $eq($profit, $is['result']) && $ov['pending'] === 0 && $ov['balanced'] && $lateInClosed === 0 && $ov['health'] === [];
        $ok($all, "  [{$a->branch_label}] books hold after: $step (" . $raw->n . ' lines)');
        if (!$all) {
            echo '     raw ', $raw->d, '/', $raw->c, ' unbalanced=', $unbalanced, ' foreign=', $foreign, ' totals=', $totals, ' tb=', json_encode($tb['balanced']), ' bs=', json_encode($bs['balanced']),
                ' is=', $is['result'], ' bsres=', $bs['result'], ' cf=', json_encode($cf['agrees']), ' cfclose=', $cf['closing'], ' raw money=', $rawMoney, ' profit=', $profit, ' pending=', $ov['pending'], ' late=', $lateInClosed, "\n";
        }
    };
    $checkBoth = function (string $step) use ($checkBooks, $A, $B) { $checkBooks($A, $step); $checkBooks($B, $step); };

    // =============================================================== 1. Opening both branches
    $start = today()->subMonthNoOverflow()->startOfMonth();
    $r = $call('GET', $acc, [], $mgrA);
    $ok($r->getStatusCode() === 302 && str_contains($r->headers->get('Location'), '/accounts/setup'), '1 no books yet: Accounts goes to the setup');
    [$p, $res] = $post('open', ['starts_on' => $start->toDateString(), 'balances' => ['safe' => 200000, 'bank' => 500000, 'mtn' => 150000], 'suppliers_owed' => 60000, 'refunds_from' => 'mtn', 'eride_from' => 'bank'], $mgrA);
    $ok(($p['ok'] ?? false) && ($res['ok'] ?? false), '1 branch A opens its books from ' . $start->format('d/m/Y') . ' (preview clean, then confirmed)');
    $r = $call('POST', "$acc/do/expense", ['date' => today()->toDateString()], $mgrB);
    $ok($r->getStatusCode() === 422, '1 branch B cannot post before its books are open');
    [$p, $res] = $post('open', ['starts_on' => $start->toDateString(), 'balances' => ['safe' => 80000, 'orange' => 40000]], $accB);
    $ok(($res['ok'] ?? false), '1 branch B opens its books (its accountant)');
    $checkBoth('opening');

    // =============================================================== 2. Last month by hand (A)
    $d = fn(int $day) => $start->copy()->addDays($day)->toDateString();
    $fuel = $code($A, '6053'); $rent = $code($A, '6222'); $repairs = $code($A, '6242'); $parcels = $code($A, '7071'); $accrued = $code($A, '4081');
    [$p, $res] = $post('expense', ['date' => $d(2), 'account_id' => $fuel, 'amount' => 45000, 'paid' => 'now', 'money_account_id' => $role($A, 'mtn'), 'vehicle_id' => (string)$busA->id, 'reference' => 'TOTAL-1'], $mgrA);
    $ok($res['ok'] ?? false, '2 fuel for the bus, paid from MTN, last month');
    $checkBooks($A, 'fuel');
    [$p, $res] = $post('expense', ['date' => $d(5), 'account_id' => $repairs, 'amount' => 120000, 'paid' => 'later', 'supplier' => 'Garage Mile 4', 'due_on' => $d(25), 'vehicle_id' => (string)$busA->id], $mgrA);
    $bill = BusAccDocument::where('agency_id', $A->id)->where('supplier', 'Garage Mile 4')->first();
    $ok(($res['ok'] ?? false) && $bill && $eq($bill->owed, 120000), '2 a garage bill to pay later (120000 owed)');
    [$p, $res] = $post('pay', ['document' => (string)$bill->id, 'date' => $d(12), 'amount' => 70000, 'money_account_id' => $role($A, 'bank'), 'reference' => 'TRF-88'], $mgrA);
    $ok(($res['ok'] ?? false) && $eq($bill->fresh()->owed, 50000), '2 part of the bill paid from the bank (50000 still owed)');
    [$p, $res] = $post('income', ['date' => $d(9), 'account_id' => $parcels, 'amount' => 18000, 'money_account_id' => $role($A, 'safe'), 'vehicle_id' => (string)$busA->id], $mgrA);
    $ok($res['ok'] ?? false, '2 parcel income into the safe');
    [$p, $res] = $post('transfer', ['date' => $d(15), 'money_account_id' => $role($A, 'safe'), 'to_account_id' => $role($A, 'bank'), 'amount' => 150000, 'fee' => 500], $mgrA);
    $ok($res['ok'] ?? false, '2 safe to bank with a 500 charge');
    [$p, $res] = $post('journal', ['date' => $start->copy()->endOfMonth()->toDateString(), 'note' => 'Rent of the month, invoice not received', 'reverse_on' => today()->startOfMonth()->toDateString(),
        'lines' => [['account_id' => $rent, 'debit' => 75000], ['account_id' => $accrued, 'credit' => 75000]]], $mgrA);
    $ok($res['ok'] ?? false, '2 rent accrued at month end, reversing on the 1st');
    $checkBooks($A, 'last month by hand');
    $is = $rep->incomeStatement($A, $start->toDateString(), $start->copy()->endOfMonth()->toDateString());
    $ok($eq($is['total_spending'], 45000 + 120000 + 500 + 75000) && $eq($is['total_income'], 18000), '2 last month: spending 240500, income 18000');

    // =============================================================== 3. Closing last month (A)
    [$p] = $post('close', ['month' => today()->startOfMonth()->toDateString()], $mgrA, false);
    $ok(!($p['ok'] ?? true), '3 the current month cannot close');
    [$p, $res] = $post('close', ['month' => $start->toDateString()], $mgrA);
    $ok(($p['ok'] ?? false) && ($res['ok'] ?? false) && collect($p['warnings'])->contains(fn($w) => str_contains($w, 'reconciled')), '3 last month closed (the preview warned it was not reconciled)');
    [$p] = $post('expense', ['date' => $d(20), 'account_id' => $fuel, 'amount' => 1000, 'paid' => 'now', 'money_account_id' => $role($A, 'mtn')], $mgrA, false);
    $ok(!($p['ok'] ?? true) && str_contains(json_encode($p['problems']), 'closed'), '3 nothing by hand in the closed month');
    [$p, $res] = $post('reopen', ['month' => $start->toDateString(), 'reason' => 'Add a forgotten fuel receipt'], $mgrA);
    $ok($res['ok'] ?? false, '3 the manager reopens it with a reason');
    [$p, $res] = $post('expense', ['date' => $d(20), 'account_id' => $fuel, 'amount' => 30000, 'paid' => 'now', 'money_account_id' => $role($A, 'mtn'), 'vehicle_id' => (string)$busA->id, 'reference' => 'TOTAL-2'], $mgrA);
    $ok($res['ok'] ?? false, '3 the forgotten fuel receipt is added');
    [$p, $res] = $post('close', ['month' => $start->toDateString()], $mgrA);
    $ok($res['ok'] ?? false, '3 and the month is closed again');
    $checkBooks($A, 'month closed');

    // =============================================================== 4. This month: tickets through Quick sell (A)
    $sell = function (array $seats, string $method, User $as) use ($call, $depA1, $dropA) {
        $r = $call('POST', '/partner/bus-agency/sell/' . $depA1->id, ['seats' => $seats, 'drop_point_id' => $dropA, 'method' => $method, 'collected' => 1, 'paper' => 1,
            'passengers' => array_map(fn($s) => ['name' => 'Passenger ' . $s, 'phone' => '6700000' . $s], $seats)], $as);
        return json_decode($r->getContent(), true) + ['_status' => $r->getStatusCode()];
    };
    $s1 = $sell(['1', '2'], 'cash', $agentA);
    $s2 = $sell(['3'], 'momo', $agentA);
    $s3 = $sell(['4'], 'cash', $mgrA);
    $ok(($s1['ok'] ?? false) && ($s2['ok'] ?? false) && ($s3['ok'] ?? false), '4 three Quick sell sales (agent: 2 cash + 1 MTN; manager: 1 cash)');
    $checkBooks($A, 'sales');
    $sync->sync($A);
    $ok($eq($ledger->balance(BusAccAccount::find($role($A, 'counter_cash')), null, ['staff_user_id' => (string)$agentA->id]), 10000), '4 the agent holds 10000 counter cash');
    $ok($eq($ledger->balance(BusAccAccount::find($role($A, 'unearned')), null, ['departure_id' => (string)$depA1->id]), 20000), '4 20000 held for tomorrow trip (not income yet)');
    // The agency cancels the manager's ticket: cash back at the counter.
    $b3 = BusBooking::where('code', $s3['code'])->first();
    $r = $call('POST', '/partner/bus-agency/bookings/' . $b3->id . '/cancel', ['reason' => 'Passenger changed plans'], $mgrA);
    $ok($r->getStatusCode() === 200, '4 a counter ticket cancelled by the agency');
    $checkBooks($A, 'cancel');
    $ok($eq($ledger->balance(BusAccAccount::find($role($A, 'counter_cash')), null, ['staff_user_id' => (string)$mgrA->id]), 0), '4 the manager cash: 5000 in, 5000 back out');

    // An app passenger pays, then cancels: refund owed, then sent from MTN (A's refunds account).
    $app = BusBooking::create(['code' => strtoupper(Str::random(8)), 'agency_id' => $A->id, 'departure_id' => $depA1->id, 'customer_id' => $custId,
        'source' => 'app', 'status' => 'accepted', 'amount' => 5000, 'commission_pct' => 5, 'commission' => 250, 'refund_amount' => 0, 'refund_status' => 'none']);
    app(\Modules\BusManagement\Service\BookingService::class)->settlePaid($app, new \Modules\BusManagement\Entities\BusPayment(['amount' => 5000, 'medium' => 'mtn momo']));
    app(\Modules\BusManagement\Service\BookingService::class)->endBooking(BusBooking::find($app->id), 'passenger', 4000);
    $checkBooks($A, 'app payment and refund owed');
    $r = $call('POST', '/partner/bus-agency/bookings/' . $app->id . '/refund-sent', ['reference' => 'MP2611'], $mgrA);
    $ok($r->getStatusCode() === 200, '4 refund marked as sent');
    $checkBooks($A, 'refund sent');

    // =============================================================== 5. The bus leaves (Boarding route)
    $depA1->update(['date' => today()->toDateString(), 'time' => now()->subMinutes(30)->format('H:i:s')]);
    $r = $call('POST', '/partner/bus-agency/boarding/' . $depA1->id . '/departed', [], $mgrA);
    $ok($r->getStatusCode() === 200, '5 the bus is marked departed');
    $checkBooks($A, 'departure');
    $is = $rep->incomeStatement($A, today()->startOfMonth()->toDateString(), today()->toDateString());
    $sales = collect($is['income'])->filter(fn($x) => in_array($x['account']->role, ['sales_counter', 'sales_online'], true))->sum('amount');
    $ok($eq($sales, 15000 + 1000), '5 sales of the trip: 15000 counter + 1000 kept on the app ticket');
    $profitBus = collect($rep->profit($A, $start->toDateString(), today()->toDateString(), 'bus'))->firstWhere('key', (string)$busA->id);
    $ok($profitBus && $eq($profitBus['fuel'], 75000) && $eq($profitBus['income'], 16000 + 18000), '5 the bus: 75000 fuel, 34000 income (tickets and parcels)');

    // =============================================================== 6. Agent cash count, reconciliation, E-Ride (A)
    [$p] = $post('count', ['staff_user_id' => (string)$agentA->id, 'counted' => 9500, 'to_account_id' => $role($A, 'safe')], $mgrA, false);
    $ok(!($p['ok'] ?? true), '6 a short count without a reason is refused in the preview');
    [$p, $res] = $post('count', ['staff_user_id' => (string)$agentA->id, 'counted' => 9500, 'to_account_id' => $role($A, 'safe'), 'note' => 'Change given twice'], $mgrA);
    $ok(($res['ok'] ?? false) && collect($p['warnings'])->contains(fn($w) => str_contains($w, '500')), '6 agent cash counted: 500 short, posted on the agent');
    $checkBooks($A, 'cash count');
    $bank = BusAccAccount::find($role($A, 'bank'));
    $lines = $svc->unmatched($bank, today()->toDateString());
    $book = $ledger->balance($bank, today()->toDateString());
    // The statement shows everything except the last movement, and 1200 of charges.
    $last = $lines->last();
    $ticked = $lines->slice(0, -1)->pluck('id')->map(fn($i) => (int)$i)->all();
    $statement = $book - ((float)$last->debit - (float)$last->credit) - 1200;
    [$p] = $post('check', ['date' => today()->toDateString(), 'account_id' => $bank->id, 'counted' => $statement, 'cleared' => $ticked, 'post_as' => 'none'], $mgrA, false);
    $ok(!($p['ok'] ?? true), '6 a reconciliation that does not agree is refused');
    [$p, $res] = $post('check', ['date' => today()->toDateString(), 'account_id' => $bank->id, 'counted' => $statement, 'cleared' => $ticked, 'post_as' => 'charges'], $mgrA);
    $ok(($res['ok'] ?? false) && $eq($ledger->balance($bank), $book - 1200), '6 bank reconciled: 1200 charges posted, the last movement left as timing');
    $ok($svc->unmatched($bank, today()->toDateString())->count() === 1, '6 only the unticked movement is offered next time');
    $checkBooks($A, 'reconciliation');
    BusStatement::create(['agency_id' => $A->id, 'month' => $start->toDateString(), 'total' => 2400, 'due_on' => today()->addDays(5)->toDateString(), 'status' => 'due']);
    $checkBooks($A, 'E-Ride statement made (for the closed month: lands today)');
    BusStatement::where('agency_id', $A->id)->update(['status' => 'paid', 'paid_at' => now()]);
    $checkBooks($A, 'E-Ride statement paid');
    $ok($eq($ledger->balance(BusAccAccount::find($role($A, 'eride'))), 0), '6 nothing owed to E-Ride');

    // =============================================================== 7. Voids
    $fuelDoc = BusAccDocument::where('agency_id', $A->id)->where('reference', 'TOTAL-2')->first();
    [$p] = $post('void', ['document' => (string)$fuelDoc->id, 'reason' => 'Entered twice'], $mgrA, false);
    $ok(($p['ok'] ?? false) && str_contains(json_encode($p['entries']), $fuelDoc->number) && !BusAccDocument::find($fuelDoc->id)->void_entry_id, '7 void preview shows the reversing entry and changes nothing');
    [$p, $res] = $post('void', ['document' => (string)$fuelDoc->id, 'reason' => 'Entered twice'], $mgrA);
    $rev = Modules\BusManagement\Entities\BusAccEntry::find($fuelDoc->fresh()->void_entry_id);
    $ok(($res['ok'] ?? false) && $rev && $rev->date->isToday(), '7 voiding a receipt of the closed month reverses it today');
    $checkBooks($A, 'void');

    // =============================================================== 8. Branch B, and the wall between branches
    $fuelB = $code($B, '6053');
    [$p, $res] = $post('expense', ['date' => today()->toDateString(), 'account_id' => $fuelB, 'amount' => 25000, 'paid' => 'now', 'money_account_id' => $role($B, 'safe')], $accB);
    $ok($res['ok'] ?? false, '8 B accountant records B fuel');
    $before = $rep->trialBalance($B, $start->toDateString(), today()->toDateString())['totals'];
    [$p] = $post('expense', ['date' => today()->toDateString(), 'account_id' => $fuel, 'amount' => 100, 'paid' => 'now', 'money_account_id' => $role($A, 'mtn')], $mgrB, false);
    $ok(!($p['ok'] ?? true), '8 B cannot post to A accounts');
    [$p] = $post('expense', ['date' => today()->toDateString(), 'account_id' => $fuelB, 'amount' => 100, 'paid' => 'now', 'money_account_id' => $role($A, 'mtn')], $mgrB, false);
    $ok(!($p['ok'] ?? true), '8 B cannot pay from A money');
    $r = $call('GET', "$acc/documents/" . $bill->id, [], $mgrB);
    $ok($r->getStatusCode() === 404, '8 B cannot open an A document');
    $r = $call('POST', "$acc/preview/pay", ['document' => (string)$bill->id, 'date' => today()->toDateString(), 'amount' => 100, 'money_account_id' => $role($B, 'safe')], $mgrB);
    $ok($r->getStatusCode() === 404 || !(json_decode($r->getContent(), true)['ok'] ?? true), '8 B cannot pay an A bill');
    $r = $call('POST', "$acc/do/void", ['document' => (string)$bill->id, 'reason' => 'not mine', 'reviewed' => 1], $mgrB);
    $ok($r->getStatusCode() === 404, '8 B cannot void an A document');
    $r = $call('GET', "$acc/journal?account=" . $role($A, 'mtn'), [], $mgrB);
    $ok($r->getStatusCode() === 200 && !str_contains($r->getContent(), 'TOTAL-1'), '8 B ledger page with an A account id shows nothing of A');
    [$p] = $post('count', ['staff_user_id' => (string)$agentA->id, 'counted' => 0, 'to_account_id' => $role($B, 'safe'), 'note' => 'x'], $mgrB, false);
    $after = $rep->trialBalance($B, $start->toDateString(), today()->toDateString())['totals'];
    $ok($before == $after, '8 B books unchanged by all those attempts');
    $ok(DB::table('bus_acc_lines as l')->join('bus_acc_accounts as x', 'x.id', '=', 'l.account_id')->whereColumn('x.agency_id', '!=', 'l.agency_id')->count() === 0, '8 no line anywhere sits on another branch account');
    [$p] = $post('reopen', ['month' => $start->toDateString(), 'reason' => 'accountant tries'], $accB, false);
    $ok(!($p['ok'] ?? true), '8 an accountant cannot reopen a month');
    $r = $call('GET', '/partner/bus-agency/today', [], $accB);
    $ok($r->getStatusCode() === 302, '8 the accountant does not get the counter screens');
    $r = $call('GET', $acc, [], $agentA);
    $ok($r->getStatusCode() === 302, '8 a counter agent does not get the accounts');
    $checkBoth('branch wall');

    // =============================================================== 9. Every page opens
    $pages = ['', '/spending', '/money', '/cash', '/reports', '/reports?tab=profit', '/reports?tab=profit&by=route', '/reports?tab=profit&by=trip', '/reports?tab=cash',
        '/reports?tab=trial', '/reports?tab=balance', '/journal', '/journal?account=__MTN__', '/settings', '/reconcile?account=__BANK__&date=' . today()->toDateString(), '/documents/__BILL__'];
    foreach ([[$A, $mgrA], [$B, $accB]] as [$br, $who]) {
        $bad = [];
        foreach ($pages as $pg) {
            $pg = str_replace(['__MTN__', '__BANK__', '__BILL__'], [$role($br, 'mtn'), $role($br, 'bank'), (string)BusAccDocument::where('agency_id', $br->id)->orderBy('created_at')->value('id')], $pg);
            $r = $call('GET', $acc . $pg . (str_contains($pg, '?') ? '&' : '?') . 'from=' . $start->toDateString(), [], $who);
            if ($r->getStatusCode() !== 200) { $bad[] = $pg . ' ' . $r->getStatusCode(); }
        }
        $ok(!$bad, "9 every accounts page opens for {$br->branch_label} " . implode(', ', $bad));
    }
    foreach (['income', 'profit', 'cash', 'trial', 'balance'] as $tab) {
        $r = $call('GET', "$acc/reports?tab=$tab&csv=1&from=" . $start->toDateString(), [], $mgrA);
        ob_start(); $r->sendContent(); $csv = ob_get_clean();
        $ok($r->getStatusCode() === 200 && substr_count($csv, "\n") >= 2, "9 CSV $tab downloads");
    }
    $r = $call('GET', '/partner/bus-company/accounts?from=' . $start->toDateString(), [], $director);
    $body = $r->getContent();
    $ok($r->getStatusCode() === 200 && str_contains($body, 'Bamenda') && str_contains($body, 'Buea'), '9 head office sees both branches side by side');
    foreach (['income', 'profit', 'cash', 'balance'] as $tab) {
        $r = $call('GET', '/partner/bus-company/accounts?branch=' . $A->id . '&tab=' . $tab . '&from=' . $start->toDateString(), [], $director);
        $ok($r->getStatusCode() === 200, "9 head office opens branch A $tab");
    }
    $regional = DB::table('bus_company_staff')->where('company_id', $companyId)->where('role', 'regional')->value('user_id');
    if ($regional) {
        $r = $call('GET', '/partner/bus-company/accounts', [], User::find($regional));
        $ok($r->getStatusCode() === 403, '9 a regional manager does not see the accounts');
    }
    $checkBoth('the end');
} catch (Throwable $e) {
    $fail++;
    echo 'ERROR ', get_class($e), ': ', $e->getMessage(), ' @ ', $e->getFile(), ':', $e->getLine(), "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
if ($fails) { echo "Failed:\n - " . implode("\n - ", $fails) . "\n"; }
