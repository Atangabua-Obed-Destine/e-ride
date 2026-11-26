<?php
/*
 * The bus money in the payment accounts' books, the Bank transfer and office-cash ways of paying,
 * and Profit by bus and trip, end to end through the real routes, rolled back (E-Ride, 2026-11-26):
 *  - ticket money (counter MoMo, app via Fapshi), a till counted into the safe, and E-Ride's statement
 *    paid move the payment accounts' books, so each book equals its ledger account; a void or an
 *    account made later is followed;
 *  - cash spent at the office with no account named posts to 571 (not an agent's till), a bank
 *    transfer to 521, and the spending forms name the ways plainly;
 *  - an expense or a supplier bill line may name a bus, and Profit by bus, trip and route adds up
 *    to the period's result.
 * Run: php tests/Ledger/bus_books_test.php
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use App\Ledger\Ledger;
use App\Ledger\Models\{ChartOfAccount, JournalEntryLine, PaymentAccount, PaymentAccountTransaction};
use App\Ledger\Services\LedgerSync;
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
    $mgr = accTestUser('Books Manager'); $agent = accTestUser('Books Agent');
    $name = 'Books Branch ' . Str::random(4);
    BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => $name, 'slug' => Str::slug($name), 'origin_city' => 'Bamenda',
        'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => [], 'commission_counter_pct' => 3, 'commission_app_pct' => 5]);
    $A = BusAgency::where('name', $name)->first();
    BusStaff::create(['agency_id' => $A->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
    BusStaff::create(['agency_id' => $A->id, 'user_id' => $agent->id, 'role' => 'counter', 'active' => true]);
    BusDestination::forceCreate(['agency_id' => $A->id, 'city' => 'Yaounde', 'active' => true, 'duration_minutes' => 360]);
    $dest = BusDestination::where('agency_id', $A->id)->first();
    BusDropPoint::forceCreate(['destination_id' => $dest->id, 'name' => 'Main office', 'fare_classic' => 6000, 'active' => true, 'sort' => 1]);
    foreach (['BK1', 'BK2'] as $reg) {
        BusVehicle::forceCreate(['agency_id' => $A->id, 'registration' => $reg . ' ' . strtoupper(Str::random(3)), 'name' => 'Coach', 'class' => 'classic', 'status' => 'ready', 'total_seats' => 30]);
    }
    [$bus1, $bus2] = BusVehicle::where('agency_id', $A->id)->orderBy('registration')->get()->all();
    BusDeparture::forceCreate(['agency_id' => $A->id, 'destination_id' => $dest->id, 'class' => 'classic', 'kind' => 'fixed',
        'date' => today()->toDateString(), 'time' => '23:59:00', 'vehicle_id' => $bus1->id,
        'seat_plan' => ['seats' => array_map('strval', range(1, 30))], 'capacity' => 30, 'status' => 'scheduled']);
    $dep = BusDeparture::where('agency_id', $A->id)->first();

    $session = $app['session']->driver();
    $call = function (string $method, string $url, array $data = [], ?User $as = null) use ($app, $kernel, $session, $mgr) {
        $req = Request::create($url, $method, $data, [], [], ['HTTP_ACCEPT' => 'text/html']);
        $app['auth']->guard('web')->setUser($as ?? $mgr);
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
    $call('GET', '/partner/bus-agency/accounting');
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1]);
    $bid = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $A->id)->value('id');
    $ok($bid && Ledger::isSetUp($bid), 'books set up');
    \App\Ledger\LedgerObserver::forget();
    $in = fn(callable $fn) => Ledger::forBusiness($bid, $fn);
    $acc = fn(string $code) => (int)$in(fn() => ChartOfAccount::where('account_code', $code)->value('id'));
    $bal = fn(string $code) => round((float)$in(fn() => JournalEntryLine::where('account_id', ChartOfAccount::where('account_code', $code)->value('id'))
        ->whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->selectRaw('COALESCE(SUM(debit - credit), 0) as b')->value('b')), 2);
    $book = fn(string $title) => round((float)$in(fn() => PaymentAccount::where('title', $title)->value('current_balance')), 2);

    // ---- 1. New books start with the Bank transfer method and Office cash.
    $ok($in(fn() => \App\Ledger\Services\DefaultAccount::id('payment_method', 'bank')) === $acc('521'), 'new books: Bank transfer maps to 521');
    $ok($in(fn() => \App\Ledger\Services\DefaultAccount::id('office_cash')) === $acc('571'), 'new books: Office cash maps to 571');
    $methods = \App\Ledger\Services\PaymentBook::methods();
    $ok(($methods['cash'] ?? '') === 'Cash' && ($methods['bank'] ?? '') === 'Bank transfer', 'the spending forms name the ways plainly (' . json_encode($methods) . ')');

    // ---- 2. Payment accounts; a counter MoMo sale and an app sale move their books.
    $call('GET', $P . 'payment-account');
    $type = fn(string $k) => $in(fn() => \App\Ledger\Models\PaymentAccountType::where('kind', $k)->value('id'));
    $call('POST', $P . 'payment-account/store', ['title' => 'Safe', 'account_type_id' => $type('cash'), 'chart_of_account_id' => $acc('571'),
        'opening_balance' => 100000, 'contra_account_id' => $acc('4711'), 'reviewed' => 1]);
    $call('POST', $P . 'payment-account/store', ['title' => 'MTN line', 'account_type_id' => $type('mobile_money'), 'chart_of_account_id' => $acc('5521'),
        'opening_balance' => 50000, 'contra_account_id' => $acc('4711'), 'reviewed' => 1]);
    $mk = fn(array $f) => BusMoneyMove::create($f + ['agency_id' => $A->id, 'booking_id' => (string)Str::uuid(), 'departure_id' => (string)$dep->id, 'kind' => 'sale', 'at' => now()]);
    $mk(['channel' => 'counter', 'method' => 'momo', 'amount' => 6000, 'staff_user_id' => $agent->id]);
    $mk(['channel' => 'counter', 'method' => 'cash', 'amount' => 6000, 'staff_user_id' => $agent->id]);
    $mk(['channel' => 'counter', 'method' => 'cash', 'amount' => 6000, 'staff_user_id' => $agent->id]);
    $appSale = $mk(['channel' => 'online', 'method' => 'fapshi', 'amount' => 6000]);
    LedgerSync::flush();
    $ok($book('MTN line') == 56000 && $eq($bal('5521'), 56000), 'counter MoMo sale: the MTN book and 5521 both 56 000 (' . $book('MTN line') . ' / ' . $bal('5521') . ')');
    $ok($in(fn() => PaymentAccountTransaction::where('reference_type', 'bus_ticket')->count()) === 1, 'one book movement "Ticket money" (the cash sales stay in the agent\'s till)');

    // An account made after the money came in gets it (Fapshi)
    $call('POST', $P . 'payment-account/store', ['title' => 'Fapshi', 'account_type_id' => $type('mobile_money'), 'chart_of_account_id' => $acc('5523'),
        'opening_balance' => 0, 'reviewed' => 1]);
    $ok($eq($book('Fapshi'), $bal('5523')) && $bal('5523') > 0, 'Fapshi account made later: its book takes the app sale already posted (' . $book('Fapshi') . ' / ' . $bal('5523') . ')');

    // The sale's amount changes: the book follows the repost
    $appSale->update(['amount' => 7000]);
    LedgerSync::flush();
    $ok($eq($book('Fapshi'), $bal('5523')) && $bal('5523') >= 6900, 'a reposted sale changes the book movement too (' . $book('Fapshi') . ')');

    // ---- 3. A till count goes into the safe's book; a void takes it out.
    $call('POST', $P . 'tills/count', ['agent_id' => $agent->id, 'counted' => 11500, 'to_account_id' => $acc('571'), 'count_date' => today()->toDateString(),
        'note' => 'Short 500', 'reviewed' => 1]);
    $ok($book('Safe') == 111500 && $eq($bal('571'), 111500), 'till counted into the safe: book and 571 both 111 500 (' . $book('Safe') . ' / ' . $bal('571') . ')');
    $tc = $in(fn() => \App\Ledger\Models\TillCount::latest('id')->first());
    $call('POST', $P . "tills/{$tc->id}/void", ['reason' => 'Counted twice', 'reviewed' => 1]);
    $ok($book('Safe') == 100000 && $eq($bal('571'), 100000), 'count voided: out of the safe book and the ledger');

    // ---- 4. E-Ride's statement paid: out of the MTN book.
    $st = BusStatement::forceCreate(['agency_id' => $A->id, 'month' => now()->startOfMonth()->toDateString(), 'total' => 2000,
        'due_on' => now()->addDays(10)->toDateString(), 'status' => 'paid', 'paid_at' => now()]);
    LedgerSync::flush();
    $ok($book('MTN line') == 54000 && $eq($bal('5521'), 54000), 'E-Ride statement paid from MTN: book and 5521 both 54 000 (' . $book('MTN line') . ')');

    // ---- 5. Office cash and bank: an expense with no account named.
    $call('POST', $P . 'account/expense-category', ['title' => 'Fuel']);
    $call('POST', $P . 'account/expense-category', ['title' => 'Rent']);
    $cat = fn($n) => $in(fn() => \App\Ledger\Models\ExpenseCategory::where('name', $n)->value('id'));
    $call('POST', $P . 'mappings/save-default', ['mappings' => [
        ['mapping_type' => 'expense_category', 'category_key' => (string)$cat('Fuel'), 'account_id' => $acc('6053')],
        ['mapping_type' => 'expense_category', 'category_key' => (string)$cat('Rent'), 'account_id' => $acc('622')]]]);
    $till5712 = $bal('5712');
    $call('POST', $P . 'account/expense', ['category' => $cat('Fuel'), 'title' => 'Diesel cash', 'amount' => 3000, 'date' => today()->toDateString(),
        'payment_method' => 'cash', 'vehicle_id' => (string)$bus1->id, 'reviewed' => 1]);
    $ok($eq($bal('571'), 97000) && $eq($bal('5712'), $till5712), 'cash spent with no account named: Cr 571 office cash, the agents\' tills untouched');
    $call('POST', $P . 'account/expense', ['category' => $cat('Rent'), 'title' => 'Rent', 'amount' => 20000, 'date' => today()->toDateString(), 'payment_method' => 'bank', 'reviewed' => 1]);
    $ok($eq($bal('521'), -20000), 'a bank transfer with no account named: Cr 521');
    $ok($in(fn() => JournalEntryLine::where('account_id', $acc('6053'))->where('vehicle_id', (string)$bus1->id)->exists()), 'the fuel cost line carries its bus');
    $call('POST', $P . 'account/expense', ['category' => $cat('Fuel'), 'title' => 'Wrong bus', 'amount' => 100, 'date' => today()->toDateString(),
        'payment_method' => 'cash', 'vehicle_id' => (string)Str::uuid(), 'reviewed' => 1]);
    $ok(!$in(fn() => \App\Ledger\Models\Expense::where('title', 'Wrong bus')->exists()), 'a bus not in the fleet is refused');

    // A supplier bill line for bus 2
    $call('POST', $P . 'account/suppliers', ['title' => 'Tyre shop', 'payment_terms_days' => 30]);
    $sup = $in(fn() => \App\Ledger\Models\ItemSupplier::where('title', 'Tyre shop')->value('id'));
    $call('POST', $P . 'account/supplier-bills', ['supplier_id' => $sup, 'kind' => 'bill', 'supplier_reference' => 'TS-1', 'bill_date' => today()->toDateString(),
        'lines' => [['expense_category_id' => $cat('Fuel'), 'description' => 'Tyres', 'amount' => 40000, 'vehicle_id' => (string)$bus2->id]], 'after' => 'draft']);
    $bill = $in(fn() => \App\Ledger\Models\SupplierBill::where('supplier_reference', 'TS-1')->first());
    $call('POST', $P . "account/supplier-bills/{$bill->id}/approve", ['reviewed' => 1]);
    $ok($in(fn() => JournalEntryLine::where('vehicle_id', (string)$bus2->id)->where('debit', 40000)->exists()), 'the supplier bill line carries its bus');

    // ---- 6. The trip leaves; Profit by bus, trip and route.
    $dep->update(['status' => 'departed', 'departed_at' => now()]);
    LedgerSync::flush();
    $report = $in(fn() => (new \App\Http\Controllers\Ledger\BusProfitController())->report('bus', today()->toDateString(), today()->toDateString()));
    $r1 = collect($report['rows'])->firstWhere('key', (string)$bus1->id);
    $r2 = collect($report['rows'])->firstWhere('key', (string)$bus2->id);
    $ok($r1 && $eq($r1['sales'], $bal('706') * -1) && $r1['trips'] === 1 && $eq($r1['running'], 3000), 'bus 1: the trip\'s ticket sales and its fuel (' . json_encode($r1) . ')');
    $ok($r2 && $eq($r2['sales'], 0) && $eq($r2['running'], 40000), 'bus 2: no trip, its tyres (' . json_encode($r2) . ')');
    $ok($report['shared'] && $eq($report['shared']['running'], 20000) && $eq($report['shared']['fees'], 2000), 'not tied to a bus: rent and E-Ride\'s commission (the voided till shortfall is gone) (' . json_encode($report['shared']) . ')');
    $ok($in(fn() => \App\Ledger\Models\Expense::where('title', 'Diesel cash')->value('payment_account_id')) == $in(fn() => PaymentAccount::where('title', 'Safe')->value('id')),
        'cash spent with no account named is linked to the one account on 571 (the safe), so its book follows');
    $income = -1 * ($bal('7%') ?? 0);
    $result = round((float)$in(fn() => DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
        ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')->where('j.business_id', $bid)->where('j.is_posted', 1)->whereNull('j.deleted_at')
        ->whereIn('a.class_number', [6, 7, 8])->sum(DB::raw('l.credit - l.debit'))), 2);
    $ok($eq($report['total']['result'], $result), 'the total is the period\'s result, classes 6 to 8 (' . $report['total']['result'] . ' / ' . $result . ')');
    $trip = $in(fn() => (new \App\Http\Controllers\Ledger\BusProfitController())->report('trip', today()->toDateString(), today()->toDateString()));
    $ok(count($trip['rows']) === 1 && $eq($trip['rows'][0]['sales'], $r1['sales']) && str_contains($trip['rows'][0]['label'], 'Yaounde'), 'by trip: the trip that left, named with its route');
    $route = $in(fn() => (new \App\Http\Controllers\Ledger\BusProfitController())->report('route', today()->toDateString(), today()->toDateString()));
    $ok(count($route['rows']) === 1 && $route['rows'][0]['label'] === 'Yaounde', 'by route: Yaounde');
    $r = $call('GET', $P . 'accounting-reports/bus-profit?by=bus');
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), $bus1->registration) && str_contains($r->getContent(), 'ledger-nav'), 'the report page opens in the menu frame (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'accounting-reports/bus-profit?by=trip&export=csv');
    $ok($r->getStatusCode() === 200, 'CSV export (' . $r->getStatusCode() . ')');

    // ---- 7. Every payment account's book equals its ledger account; Ledger health is clean.
    $off = $in(function () {
        $out = [];
        foreach (PaymentAccount::get() as $p) {
            $led = round((float)JournalEntryLine::where('account_id', $p->chart_of_account_id)->whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->sum(DB::raw('debit - credit')), 2);
            if (abs($led - (float)$p->fresh()->current_balance) > 0.01) {
                $out[] = $p->title . ' book ' . $p->current_balance . ' ledger ' . $led;
            }
        }
        return $out;
    });
    $ok($off === [], 'every payment account book equals its ledger account (' . implode('; ', $off) . ')');
    $rebuilt = $in(fn() => \App\Ledger\ERide\BusBook::rebuild());
    $ok($book('MTN line') == 54000 && $book('Safe') == 97000, 'rebuilding the bus movements gives the same books (' . $rebuilt . ' movements)');
    $health = $in(fn() => (new \App\Ledger\Services\LedgerHealth())->checks());
    $bad = collect($health)->filter(fn($c) => ($c['count'] ?? 0) > 0 && in_array($c['severity'] ?? '', ['critical'], true))->pluck('key')->all();
    $ok($bad === [], 'Ledger health: no critical finding (' . implode(', ', $bad) . ')');
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
