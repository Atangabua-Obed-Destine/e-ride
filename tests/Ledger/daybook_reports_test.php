<?php
/*
 * The Daybook and the Accounting reports on a bus branch's books, end to end through the real routes,
 * all rolled back: a payment account with its opening balance, an income, an expense, a fund transfer,
 * two supplier bills (one paid, one part paid), bus ticket money (counter cash, app, a refund at the
 * counter) and E-Ride's statement; then the daybook's figures for the day and the month agree with the
 * posted ledger and the payment accounts, its summary and analysis add up to the book, every report
 * opens (HTML, PDF, Excel) and its totals agree with the trial balance where they should.
 *
 * Run: php tests/Ledger/daybook_reports_test.php
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use App\Ledger\Ledger;
use App\Ledger\Models\{ChartOfAccount, FiscalYear, JournalEntryLine, PaymentAccount, SupplierBill};
use App\Ledger\Services\{AgingReportService, BudgetActualsService, CustomerCollection, DaybookService, FinancialHealth, LedgerSync};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAgency, BusDeparture, BusDestination, BusDropPoint, BusMoneyMove, BusStaff, BusStatement, BusVehicle};
use Modules\UserManagement\Entities\User;

$pass = 0; $fail = 0; $fails = [];
$ok = function ($c, $label) use (&$pass, &$fail, &$fails) { if ($c) { $pass++; } else { $fail++; $fails[] = $label; } print(($c ? 'PASS ' : 'FAIL ') . $label . "\n"); };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;

DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    \App\Ledger\LedgerObserver::forget();
    $mgrA = accTestUser('Daybook Manager A'); $agentA = accTestUser('Daybook Agent A'); $mgrB = accTestUser('Daybook Manager B');
    $mk = function (string $name, User $mgr, ?User $agent) {
        BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => $name, 'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)), 'origin_city' => 'Bamenda',
            'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => [], 'commission_counter_pct' => 3, 'commission_app_pct' => 5]);
        $a = BusAgency::where('name', $name)->first();
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
        if ($agent) {
            BusStaff::create(['agency_id' => $a->id, 'user_id' => $agent->id, 'role' => 'counter', 'active' => true]);
        }
        BusDestination::forceCreate(['agency_id' => $a->id, 'city' => 'Yaounde', 'active' => true, 'duration_minutes' => 360]);
        $dest = BusDestination::where('agency_id', $a->id)->first();
        BusDropPoint::forceCreate(['destination_id' => $dest->id, 'name' => 'Main office', 'fare_classic' => 5000, 'active' => true, 'sort' => 1]);
        BusVehicle::forceCreate(['agency_id' => $a->id, 'registration' => 'DBK ' . strtoupper(Str::random(3)), 'name' => 'Coach', 'class' => 'classic', 'status' => 'ready', 'total_seats' => 10]);
        $bus = BusVehicle::where('agency_id', $a->id)->first();
        BusDeparture::forceCreate(['agency_id' => $a->id, 'destination_id' => $dest->id, 'class' => 'classic', 'kind' => 'fixed',
            'date' => today()->addDay()->toDateString(), 'time' => '10:00:00', 'vehicle_id' => $bus->id,
            'seat_plan' => ['seats' => array_map('strval', range(1, 10))], 'capacity' => 10, 'status' => 'scheduled']);
        return [$a, BusDeparture::where('agency_id', $a->id)->first()];
    };
    [$A, $depA] = $mk('Daybook Branch Bamenda', $mgrA, $agentA);
    [$B] = $mk('Daybook Branch Kumba', $mgrB, null);

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
    $P = '/partner/accounting/';
    $today = today()->toDateString();

    // ---- 1. Branch A's books, its categories and payment accounts.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrA);
    $bidA = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $A->id)->value('id');
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrA);
    $ok($bidA > 0 && Ledger::isSetUp($bidA), 'branch A books opened and set up');
    $in = fn(callable $fn) => Ledger::forBusiness($bidA, $fn);
    $acc = fn(string $code) => (int)$in(fn() => ChartOfAccount::where('account_code', $code)->value('id'));
    // the trial balance, account by account (Dr - Cr of the posted lines), by code prefix
    $tb = fn(string $prefix, ?string $from = null, ?string $to = null, array $not = []) => round((float)$in(fn() => DB::table('journal_entry_lines as l')
        ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
        ->where('l.business_id', $bidA)->where('j.business_id', $bidA)->where('j.is_posted', 1)->whereNull('j.deleted_at')
        ->where('a.account_code', 'like', $prefix . '%')->when($not, fn($q) => $q->where(fn($w) => array_map(fn($n) => $w->where('a.account_code', 'not like', $n . '%'), $not)))
        ->when($from, fn($q) => $q->where('j.entry_date', '>=', $from))->when($to, fn($q) => $q->where('j.entry_date', '<=', $to))
        ->sum(DB::raw('l.debit - l.credit'))), 2);

    $call('POST', $P . 'account/income-category', ['title' => 'Parcel desk'], $mgrA);
    $call('POST', $P . 'account/expense-category', ['title' => 'Fuel'], $mgrA);
    $call('POST', $P . 'account/expense-category', ['title' => 'Office'], $mgrA);
    [$catParcel, $catFuel, $catOffice] = $in(fn() => [\App\Ledger\Models\IncomeCategory::where('name', 'Parcel desk')->value('id'),
        \App\Ledger\Models\ExpenseCategory::where('name', 'Fuel')->value('id'), \App\Ledger\Models\ExpenseCategory::where('name', 'Office')->value('id')]);
    $call('POST', $P . 'mappings/save-default', ['mappings' => [
        ['mapping_type' => 'income_category', 'category_key' => (string)$catParcel, 'account_id' => $acc('707')],
        ['mapping_type' => 'expense_category', 'category_key' => (string)$catFuel, 'account_id' => $acc('6053')],
        ['mapping_type' => 'expense_category', 'category_key' => (string)$catOffice, 'account_id' => $acc('6055')],
    ]], $mgrA);
    $call('GET', $P . 'payment-account', [], $mgrA);
    [$cashType, $mobileType] = $in(fn() => [\App\Ledger\Models\PaymentAccountType::where('kind', 'cash')->value('id'), \App\Ledger\Models\PaymentAccountType::where('kind', 'mobile_money')->value('id')]);
    $call('POST', $P . 'payment-account/store', ['title' => 'Main till', 'account_type_id' => $cashType, 'chart_of_account_id' => $acc('571'),
        'opening_balance' => 100000, 'contra_account_id' => $acc('4711'), 'reviewed' => 1], $mgrA);
    $call('POST', $P . 'payment-account/store', ['title' => 'MTN agency line', 'account_type_id' => $mobileType, 'chart_of_account_id' => $acc('5521'), 'opening_balance' => 0, 'reviewed' => 1], $mgrA);
    [$tillId, $mtnId] = $in(fn() => [PaymentAccount::where('title', 'Main till')->value('id'), PaymentAccount::where('title', 'MTN agency line')->value('id')]);
    $ok($catParcel && $catFuel && $tillId && $mtnId, 'categories and two payment accounts made through the routes');

    // ---- 2. Income, expense, a fund transfer, two supplier bills.
    $call('POST', $P . 'account/income', ['category' => $catParcel, 'title' => 'Parcel desk week 1', 'amount' => 25000, 'date' => $today, 'payment_method' => 'cash', 'payment_account_id' => $tillId, 'invoice_id' => 'PD-001', 'reviewed' => 1], $mgrA);
    $call('POST', $P . 'account/expense', ['category' => $catFuel, 'title' => 'Diesel', 'amount' => 30000, 'date' => $today, 'payment_method' => 'cash', 'payment_account_id' => $tillId, 'reviewed' => 1], $mgrA);
    $call('POST', $P . 'payment-account-transfer/store', ['from_account_id' => $tillId, 'to_account_id' => $mtnId, 'amount' => 60000, 'transfer_date' => $today, 'reviewed' => 1], $mgrA);
    $call('POST', $P . 'account/suppliers', ['title' => 'Bamenda Tyres', 'payment_terms_days' => 30], $mgrA);
    $supId = $in(fn() => \App\Ledger\Models\ItemSupplier::where('title', 'Bamenda Tyres')->value('id'));
    $bill = function (string $ref, int $cat, float $amount) use ($call, $P, $mgrA, $supId, $today, $in) {
        $call('POST', $P . 'account/supplier-bills', ['supplier_id' => $supId, 'kind' => 'bill', 'supplier_reference' => $ref, 'bill_date' => $today,
            'lines' => [['expense_category_id' => $cat, 'description' => $ref, 'amount' => $amount]], 'after' => 'draft'], $mgrA);
        $b = $in(fn() => SupplierBill::where('supplier_reference', $ref)->first());
        $call('POST', $P . "account/supplier-bills/{$b->id}/approve", ['reviewed' => 1], $mgrA);
        return $in(fn() => SupplierBill::find($b->id));
    };
    $bill1 = $bill('BT-1', $catFuel, 50000);
    $bill2 = $bill('BT-2', $catOffice, 10000);
    $ok($bill1 && $bill1->isApproved() && $bill2 && $bill2->isApproved(), 'two supplier bills approved (50 000 and 10 000)');
    $call('POST', $P . 'account/supplier-payments', ['supplier_id' => $supId, 'payment_date' => $today, 'amount' => 20000, 'payment_method' => 'cash', 'payment_account_id' => $tillId,
        'cash_account_id' => $acc('571'), 'allocations' => [$bill1->id => 20000], 'reviewed' => 1], $mgrA);
    $call('POST', $P . 'account/supplier-payments', ['supplier_id' => $supId, 'payment_date' => $today, 'amount' => 10000, 'payment_method' => 'momo', 'payment_account_id' => $mtnId,
        'cash_account_id' => $acc('5521'), 'allocations' => [$bill2->id => 10000], 'reviewed' => 1], $mgrA);
    $ok($in(fn() => app(\App\Ledger\Services\SupplierPayables::class)->paymentState(SupplierBill::find($bill2->id))) === 'paid'
        && $in(fn() => app(\App\Ledger\Services\SupplierPayables::class)->paymentState(SupplierBill::find($bill1->id))) === 'part_paid', 'bill 2 paid, bill 1 part paid (30 000 owed)');

    // ---- 3. Bus ticket money (counter cash, app, a refund at the counter) and E-Ride's statement.
    $bookingX = (string)Str::uuid(); $bookingY = (string)Str::uuid();
    $move = fn(array $f) => BusMoneyMove::create($f + ['agency_id' => $A->id, 'departure_id' => (string)$depA->id, 'at' => now()]);
    $move(['booking_id' => $bookingX, 'kind' => 'sale', 'channel' => 'counter', 'method' => 'cash', 'amount' => 10000, 'staff_user_id' => $agentA->id]);
    $move(['booking_id' => $bookingY, 'kind' => 'sale', 'channel' => 'app', 'method' => 'fapshi', 'amount' => 8000]);
    $move(['booking_id' => $bookingX, 'kind' => 'cash_refund', 'channel' => 'counter', 'method' => 'cash', 'amount' => 2000, 'staff_user_id' => $agentA->id]);
    $stmt = BusStatement::create(['agency_id' => $A->id, 'month' => today()->startOfMonth()->toDateString(), 'app_tickets' => 1, 'app_fares' => 8000, 'app_commission' => 400,
        'counter_tickets' => 1, 'counter_fares' => 10000, 'counter_commission' => 300, 'total' => 1500, 'due_on' => today()->addDays(15)->toDateString(), 'status' => 'due', 'reference' => 'ST-DBK-1']);
    LedgerSync::flush();
    $ok($eq($tb('5712'), 8000) && $eq($tb('5523'), 8000) && $eq($tb('477'), -16000), 'ticket money posted: till 8 000, Fapshi 8 000, held 16 000');
    $ok($eq($tb('401'), -31500) && $eq($tb('632'), 1500), "E-Ride's statement posted: 401 = 30 000 (bill 1) + 1 500 (E-Ride)");
    $failed = $in(fn() => DB::table('transaction_mappings')->where('business_id', $bidA)->where('status', 'failed')->pluck('error')->all());
    $ok(!$failed, 'nothing failed to post (' . implode(' | ', $failed) . ')');

    // ---- 4. The daybook against the ledger and the payment accounts.
    $cash5 = fn(?string $from = null, ?string $to = null) => $tb('5', $from, $to, ['59']);
    $r = $call('GET', $P . 'daybook', [], $mgrA);
    $html = $r->getContent();
    $ok($r->getStatusCode() === 200, 'daybook opens (' . $r->getStatusCode() . ')');
    $period = $in(fn() => FiscalYear::periodForDate($today));
    [$from, $to] = [$period->start_date->toDateString(), $period->end_date->toDateString()];
    $book = $in(fn() => app(DaybookService::class)->rows($from, $to));
    $sum = fn(array $rows, string $dir, ?string $day = null) => round(array_sum(array_map(fn($x) => $x['direction'] === $dir && (!$day || $x['date'] === $day) ? $x['amount'] : 0, $rows)), 2);
    $ledgerIn = fn(?string $f, ?string $t) => round((float)$in(fn() => DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
        ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')->where('l.business_id', $bidA)->where('j.is_posted', 1)->whereNull('j.deleted_at')
        ->where('a.account_code', 'like', '5%')->where('a.account_code', 'not like', '59%')->where('j.entry_date', '>=', $f)->where('j.entry_date', '<=', $t)
        ->selectRaw('SUM(GREATEST(l.debit - l.credit, 0)) i, SUM(GREATEST(l.credit - l.debit, 0)) o')->first()->i), 2);
    $ledgerOut = fn(?string $f, ?string $t) => round((float)$in(fn() => DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
        ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')->where('l.business_id', $bidA)->where('j.is_posted', 1)->whereNull('j.deleted_at')
        ->where('a.account_code', 'like', '5%')->where('a.account_code', 'not like', '59%')->where('j.entry_date', '>=', $f)->where('j.entry_date', '<=', $t)
        ->selectRaw('SUM(GREATEST(l.credit - l.debit, 0)) o')->value('o')), 2);
    $ok($eq($sum($book, 'in'), 203000) && $eq($sum($book, 'out'), 122000), 'the month\'s book: 203 000 in, 122 000 out (' . $sum($book, 'in') . ' / ' . $sum($book, 'out') . ')');
    $ok($eq($sum($book, 'in'), $ledgerIn($from, $to)) && $eq($sum($book, 'out'), $ledgerOut($from, $to)), 'the month: money in and out = the posted cash, bank and mobile money lines');
    $ok($eq($sum($book, 'in', $today), $ledgerIn($today, $today)) && $eq($sum($book, 'out', $today), $ledgerOut($today, $today)), 'the day: money in and out = the posted lines of the day');
    $ok($eq($sum($book, 'in') - $sum($book, 'out'), $cash5($from, $to)), 'net movement = the change of class 5 in the trial balance');
    $money = fn($v) => $in(fn() => Ledger::money($v));
    $ok(str_contains($html, 'id="db-in">' . $money(203000) . '<') && str_contains($html, 'id="db-out">' . $money(122000) . '<') && str_contains($html, 'id="db-closing">' . $money(81000) . '<'),
        'the page shows 203 000 in, 122 000 out, 81 000 closing');
    $balances = $in(fn() => app(DaybookService::class)->balances($from, $to, app(DaybookService::class)->cashIds()));
    $ok($eq($balances[$acc('571')]['closing'] ?? null, $in(fn() => PaymentAccount::find($tillId)->current_balance)) && $eq($balances[$acc('571')]['closing'], 15000),
        'Main till: the daybook\'s closing 15 000 = the payment account\'s book');
    $ok($eq($balances[$acc('5521')]['closing'] ?? null, $in(fn() => PaymentAccount::find($mtnId)->current_balance)) && $eq($balances[$acc('5521')]['closing'], 50000),
        'MTN line: the daybook\'s closing 50 000 = the payment account\'s book');
    $ok(str_contains($html, 'Main till') && str_contains($html, 'MTN agency line'), 'the boxes carry the names of their payment accounts');
    $src = array_count_values(array_column($book, 'source'));
    $ok(($src['bus_move_counter'] ?? 0) === 2 && ($src['bus_move_app'] ?? 0) === 1 && ($src['income'] ?? 0) === 1 && ($src['expense'] ?? 0) === 1
        && ($src['supplier_payment'] ?? 0) === 2 && ($src['account_fund_transfer'] ?? 0) === 2 && ($src['account_opening_balance'] ?? 0) === 1, 'every row names its document (' . json_encode($src) . ')');
    $ok(str_contains($html, 'Ticket money, counter') && str_contains($html, 'Ticket money, app') && str_contains($html, 'Parcel desk — Parcel desk week 1')
        && str_contains($html, 'Bamenda Tyres — Supplier payment'), 'the page names ticket money by channel, the income and the supplier payment');
    $unanalysed = array_filter($book, fn($x) => $x['unanalysed']);
    $ok(count($unanalysed) === 1 && $eq(array_values($unanalysed)[0]['amount'], 100000), 'the opening balance against 4711 is the one unanalysed row');
    $ok(count(array_filter($book, fn($x) => $x['is_transfer'])) === 2, 'the transfer is two transfer rows');

    // the Monthly Summary
    $r = $call('GET', $P . 'daybook/summary', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'Monthly Summary opens (' . $r->getStatusCode() . ')');
    $sumData = $in(fn() => app(DaybookService::class)->monthlySummary(FiscalYear::forDate($today), FiscalYear::periodForDate($today), app(DaybookService::class)->cashIds()));
    $ok($eq($sumData['net']['monthly'], $sum($book, 'in') - $sum($book, 'out')), 'the summary\'s net balance = the book\'s net movement (' . $sumData['net']['monthly'] . ')');
    $byDoc = $sumData['by_document'];
    $ok($eq($byDoc['bus_move_counter']['in'] ?? 0, 10000) && $eq($byDoc['bus_move_counter']['out'] ?? 0, 2000) && $eq($byDoc['bus_move_app']['in'] ?? 0, 8000),
        'by document: ticket money counter 10 000 in / 2 000 out, app 8 000 in');
    $ok($eq(array_sum(array_column($byDoc, 'in')), $sum($book, 'in')) && $eq(array_sum(array_column($byDoc, 'out')), $sum($book, 'out')), 'by document adds up to the book');

    // Trends & Analysis
    $r = $call('GET', $P . 'daybook/analysis', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'Trends & Analysis opens (' . $r->getStatusCode() . ')');
    $an = $in(fn() => app(DaybookService::class)->analysis(FiscalYear::forDate($today), app(DaybookService::class)->cashIds()));
    $ok($eq($an['totals']['in'], 143000) && $eq($an['totals']['out'], 62000), 'analysis: transfers left out (143 000 in, 62 000 out)');
    $coll = $in(fn() => app(CustomerCollection::class)->forYear(FiscalYear::forDate($today)->start_date->toDateString(), FiscalYear::forDate($today)->end_date->toDateString(), $an['rows'], $an['months']));
    $ok($coll['sales']['total'] === 2 && $coll['sales']['full'] === 2 && $eq($coll['billed'], 16000) && $eq($coll['collected'], 16000) && $eq($coll['outstanding'], 0),
        'who has paid: 2 tickets, 16 000 net of the refund, all paid');
    $ok($eq(array_sum(array_column($coll['by_month'], 'cash')), 16000), 'ticket money month by month = 16 000 (the book\'s ticket rows)');

    // PDF, Excel, the month route, the meeting pack
    $r = $call('GET', $P . 'daybook/pdf', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains((string)$r->headers->get('Content-Type'), 'pdf') && str_starts_with($r->getContent(), '%PDF'), 'daybook PDF downloads');
    $r = $call('GET', $P . 'daybook/excel', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains((string)$r->headers->get('Content-Disposition'), '.xlsx'), 'daybook Excel downloads');
    $r = $call('POST', $P . 'daybook/analysis/pdf', ['charts' => []], $mgrA);
    $ok($r->getStatusCode() === 200 && str_starts_with($r->getContent(), '%PDF'), 'analysis meeting pack (PDF) downloads');
    $r = $call('GET', $P . 'daybook/month/' . today()->format('Y-m'), [], $mgrA);
    $ok($r->getStatusCode() === 302 && str_contains((string)$r->headers->get('Location'), 'period_id=' . $period->id), 'daybook/month/YYYY-MM opens the month');
    $r = $call('GET', $P . 'daybook?account_id=' . $acc('571'), [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'id="db-closing">' . $money(15000) . '<'), 'one cash box: Main till closes at 15 000');

    // ---- 5. The accounting reports.
    foreach (['accounting-reports', 'accounting-reports/financial-health', 'accounting-reports/budget-vs-actual', 'accounting-reports/receivables-aging',
                 'accounting-reports/payables-aging', 'accounting-reports/cash-flow-statement', 'accounting-reports/comparative-cash-flow', 'general-ledger/trial-balance'] as $p) {
        $r = $call('GET', $P . $p, [], $mgrA);
        $ok($r->getStatusCode() === 200, "$p opens (" . $r->getStatusCode() . ')');
    }
    $html = $call('GET', $P . 'accounting-reports/payables-aging', [], $mgrA)->getContent();
    $ok(str_contains($html, 'Bamenda Tyres') && str_contains($html, 'E-Ride') && str_contains($html, 'ST-DBK-1'), 'payables aging lists the supplier and E-Ride\'s statement');
    $pay = json_decode($call('GET', $P . 'accounting-reports/payables-aging', [], $mgrA, true)->getContent(), true)['data'] ?? [];
    $ok($eq($pay['summary']['total'] ?? null, 31500) && $eq($pay['ledger_balance'] ?? null, -$tb('40', null, null, ['409'])) && $eq($pay['difference'] ?? 1, 0),
        'payables aging: 31 500 owed = the 401 balance of the trial balance, no difference');
    $ok(empty($pay['contacts_differing']), 'payables aging: supplier by supplier the documents agree with the ledger');
    $rec = json_decode($call('GET', $P . 'accounting-reports/receivables-aging', [], $mgrA, true)->getContent(), true)['data'] ?? [];
    $ok($eq($rec['summary']['total'] ?? 1, 0) && $eq($rec['ledger_balance'] ?? 1, $tb('411')) && $eq($rec['difference'] ?? 1, 0), 'receivables aging: nothing sold on account, 411 empty');
    $later = today()->addDays(31)->toDateString();
    $pay = json_decode($call('GET', $P . 'accounting-reports/payables-aging?as_of_date=' . $later, [], $mgrA, true)->getContent(), true)['data'] ?? [];
    $ok($eq($pay['summary']['current'] ?? null, 31500), 'a month later both are overdue by under 30 days (current band)');
    $cf = json_decode($call('GET', $P . 'accounting-reports/cash-flow-statement', [], $mgrA, true)->getContent(), true)['data'] ?? [];
    $ok($eq($cf['ending_cash_balance'] ?? null, $cash5()) && $eq($cf['difference'] ?? 1, 0), 'cash flow: ending cash = class 5 of the trial balance (' . ($cf['ending_cash_balance'] ?? '?') . '), no difference');
    $ok($eq(($cf['ending_cash_balance'] ?? 0) - ($cf['beginning_cash_balance'] ?? 0), $cash5($from, $to)), 'cash flow: the change in cash = the year\'s class 5 movement');
    $h = $in(fn() => (new FinancialHealth())->summary($today));
    $ok($eq($h['cash'], $cash5()) && $eq($h['cash'], 81000), 'financial health: cash 81 000 = class 5');
    $ok($eq($h['payables']['owed'], 31500) && $eq($h['payables']['overdue'], 0), 'financial health: 31 500 owed to suppliers and E-Ride, nothing overdue today');
    $h2 = $in(fn() => (new FinancialHealth())->summary($later));
    $ok($eq($h2['payables']['overdue'], 31500), 'financial health a month later: bill and statement overdue (31 500)');
    $ok(collect($h['indicators'])->firstWhere('key', 'collection')['state'] === FinancialHealth::OK, 'financial health: nothing invoiced on account is nothing to chase');
    $ok($eq($h['income'], -$tb('7')) && $eq($h['expenses'], $tb('6')), 'financial health: income and expenses = classes 7 and 6 of the trial balance');
    $ba = $in(fn() => app(BudgetActualsService::class)->forPeriod($from, $to));
    $ok($eq($ba['unallocated']['income'] ?? 0, -$tb('7', $from, $to)) && $eq($ba['unallocated']['expenditure'] ?? 0, $tb('6', $from, $to)),
        'budget vs actual (no budget): actuals = classes 7 and 6, all unallocated');
    $mv = $in(fn() => app(BudgetActualsService::class)->movements($from, $to));
    $ok($eq($mv->where('expense_category_id', $catFuel)->sum('amount'), 30000), 'budget actuals: the expense carries its category (Fuel 30 000)');
    $html = $call('GET', $P . 'accounting-reports/budget-vs-actual', [], $mgrA)->getContent();
    $ok(str_contains($html, e(__('ledger_budget.bva_no_budget', ['year' => FiscalYear::forDate($today)->name]))), 'budget vs actual says no budget covers the year');
    $r = $call('GET', $P . 'accounting-reports/budget-vs-actual?export=excel', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'budget vs actual Excel downloads');
    foreach (['payables-aging', 'receivables-aging', 'cash-flow', 'comparative-cash-flow'] as $rep) {
        $r = $call('GET', $P . 'accounting-reports/export-pdf?report=' . $rep, [], $mgrA);
        $ok($r->getStatusCode() === 200 && str_starts_with($r->getContent(), '%PDF'), "$rep PDF downloads");
        $r = $call('GET', $P . 'accounting-reports/export-excel?report=' . $rep, [], $mgrA);
        $ok($r->getStatusCode() === 200 && str_contains((string)$r->headers->get('Content-Disposition'), '.xlsx'), "$rep Excel downloads");
    }
    $in(function () use ($ok) {
        $t = JournalEntryLine::whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->selectRaw('SUM(debit) d, SUM(credit) c')->first();
        $ok(abs($t->d - $t->c) < 0.01, 'trial balance: debits = credits');
    });

    // ---- 6. A second branch sees none of it.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrB);
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrB);
    foreach (['daybook', 'daybook/summary', 'daybook/analysis', 'accounting-reports/payables-aging'] as $p) {
        $r = $call('GET', $P . $p, [], $mgrB);
        $ok($r->getStatusCode() === 200 && !str_contains($r->getContent(), 'Main till') && !str_contains($r->getContent(), 'Bamenda Tyres') && !str_contains($r->getContent(), 'ST-DBK-1'),
            "branch B's $p shows nothing of branch A");
    }
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
