<?php
// Bus branch accounting, end to end through the real services, all rolled back at the end.
require __DIR__ . '/_boot.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\{BusAccAccount, BusAccDocument, BusAccEntry, BusAccLine, BusAgency, BusBooking, BusDeparture, BusMoneyMove, BusPayment, BusStaff, BusStatement, BusTicket, BusSeatClaim, BusVehicle, BusDestination};
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Service\Accounting\{AccountingPreview, AccountingService, AccReports, Ledger, LedgerSync};

$pass = 0; $fail = 0;
$ok = function ($c, $label) use (&$pass, &$fail) { $c ? $pass++ : $fail++; print(($c ? "PASS " : "FAIL ") . $label . "\n"); };
$throws = function (callable $f, string $needle = '') { try { $f(); return false; } catch (ValidationException $e) { return $needle === '' || str_contains(json_encode($e->errors(), JSON_UNESCAPED_UNICODE), $needle); } };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;

DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $ledger = app(Ledger::class); $sync = app(LedgerSync::class); $svc = app(AccountingService::class); $preview = app(AccountingPreview::class); $rep = app(AccReports::class);
    $bookings = app(BookingService::class);
    $manager = accTestUser('Acc Manager');
    $agentUser = accTestUser('Acc Agent');
    auth()->setUser($manager);
    $cust = (string)accTestUser('Acc Customer', 'customer')->id;

    $agency = BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => 'Acc Test Bus', 'slug' => 'acc-' . Str::lower(Str::random(5)), 'origin_city' => 'Bamenda', 'status' => 'live', 'webhook_code' => Str::lower(Str::random(16))]);
    BusStaff::whereIn('user_id', [$manager->id, $agentUser->id])->delete();
    BusStaff::create(['agency_id' => $agency->id, 'user_id' => $manager->id, 'role' => 'manager', 'active' => true]);
    BusStaff::create(['agency_id' => $agency->id, 'user_id' => $agentUser->id, 'role' => 'counter', 'active' => true]);
    $dest = BusDestination::forceCreate(['id' => (string)Str::uuid(), 'agency_id' => $agency->id, 'city' => 'Douala', 'active' => true, 'duration_minutes' => 420]);
    $bus = BusVehicle::forceCreate(['id' => (string)Str::uuid(), 'agency_id' => $agency->id, 'registration' => 'NW 777 AC', 'name' => 'Acc coach', 'class' => 'classic', 'status' => 'ready', 'total_seats' => 30]);
    $mkDep = fn(string $date, string $time = '08:00:00') => BusDeparture::forceCreate(['id' => (string)Str::uuid(), 'agency_id' => $agency->id, 'destination_id' => $dest->id, 'class' => 'classic', 'kind' => 'fixed',
        'date' => $date, 'time' => $time, 'vehicle_id' => (string)$bus->id, 'seat_plan' => ['seats' => ['1', '2', '3', '4', '5', '6']], 'capacity' => 6, 'status' => 'scheduled']);
    $mkBooking = function (BusDeparture $d, array $a) use ($agency, $cust) {
        return BusBooking::create(array_merge(['code' => strtoupper(Str::random(8)), 'agency_id' => $agency->id, 'departure_id' => $d->id, 'customer_id' => $cust,
            'source' => 'app', 'status' => 'accepted', 'amount' => 6000, 'commission_pct' => 5, 'commission' => 300, 'refund_amount' => 0, 'refund_status' => 'none'], $a));
    };

    // ---- 0. Money moves are written as things happen, before any books exist.
    $past = $mkDep(today()->subDays(5)->toDateString());
    $b0 = $mkBooking($past, ['source' => 'counter', 'status' => 'paid', 'payment_method' => 'cash', 'paid_at' => now()->subDays(6), 'staff_user_id' => $agentUser->id]);
    $future = $mkDep(today()->addDays(2)->toDateString());
    $bPre = $mkBooking($future, ['source' => 'counter', 'status' => 'paid', 'payment_method' => 'cash', 'paid_at' => now()->subDays(4), 'staff_user_id' => $agentUser->id, 'amount' => 7000]);
    $ok(BusMoneyMove::where('booking_id', $b0->id)->where('kind', 'sale')->where('amount', 6000)->exists(), '0 a counter sale writes a sale move (cash, agent)');

    // ---- 1. Opening the books (3 days ago), with its preview.
    $start = today()->subDays(3)->toDateString();
    $before = BusAccDocument::count();
    $pv = $preview->run($agency, 'open', fn() => $svc->open($agency, ['starts_on' => $start, 'balances' => ['safe' => 10000, 'mtn' => 50000], 'suppliers_owed' => 4000], (string)$manager->id));
    $ok($pv['ok'] && count($pv['entries']) >= 1, '1 opening preview is clean and shows the entry');
    $ok(BusAccDocument::count() === $before && !$ledger->books($agency), '1 the preview left nothing behind (no books, no document)');
    $openLines = collect($pv['entries'][0]['lines'] ?? []);
    $ok($openLines->contains(fn($l) => str_starts_with($l['account'], '4771') && $l['credit'] !== ''), '1 the ticket paid before the start for a trip ahead is held on 4771 in the opening');
    $doc = $svc->open($agency, ['starts_on' => $start, 'balances' => ['safe' => 10000, 'mtn' => 50000], 'suppliers_owed' => 4000], (string)$manager->id);
    $books = $ledger->books($agency);
    $ok($books && BusAccAccount::where('agency_id', $agency->id)->count() === count(Modules\BusManagement\Lib\AccChart::ACCOUNTS), '1 books open with the full chart');
    $a = fn($role) => $ledger->account($agency, $role);
    $ok($eq($ledger->balance($a('unearned')), 7000) && $eq($ledger->balance($a('capital')), 10000 + 50000 - 4000 - 7000), '1 opening: 4771 = 7000 held, capital balances it (49000)');
    $ok($throws(fn() => $svc->open($agency, ['starts_on' => $start], (string)$manager->id), 'already open'), '1 the books open once');
    $ok(BusMoneyMove::where('booking_id', $b0->id)->value('skipped') === 'before_start', '1 a move before the start is left to the opening');

    // ---- 2. Counter sale, app sale, refunds.
    $d1 = $mkDep(today()->addDay()->toDateString());
    $c1 = $mkBooking($d1, ['source' => 'counter', 'status' => 'paid', 'payment_method' => 'cash', 'paid_at' => now(), 'staff_user_id' => $agentUser->id, 'amount' => 12000]);
    $c2 = $mkBooking($d1, ['source' => 'counter', 'status' => 'paid', 'payment_method' => 'cash', 'paid_at' => now(), 'staff_user_id' => $agentUser->id, 'amount' => 6000]);
    $c3 = $mkBooking($d1, ['source' => 'counter', 'status' => 'paid', 'payment_method' => 'momo', 'paid_at' => now(), 'staff_user_id' => $agentUser->id, 'amount' => 6000]);
    $app1 = $mkBooking($d1, ['source' => 'app']);
    $bookings->settlePaid(BusBooking::find($app1->id), new BusPayment(['amount' => 6000, 'medium' => 'mobile money']));
    $app2 = $mkBooking($d1, ['source' => 'web']);
    $bookings->settlePaid(BusBooking::find($app2->id), new BusPayment(['amount' => 6000, 'medium' => 'orange money']));
    // Paid twice: the second payment is owed back.
    $bookings->settlePaid(BusBooking::find($app2->id), new BusPayment(['amount' => 6000, 'medium' => 'orange money']));
    $counts = $sync->sync($agency);
    $ok(($counts['moves'] ?? 0) >= 6, '2 sync posts the new money moves (' . ($counts['moves'] ?? 0) . ')');
    $ok($eq($ledger->balance($a('counter_cash'), null, ['staff_user_id' => (string)$agentUser->id]), 12000 + 6000), '2 the agent holds 18000 in counter cash (the sale before the start is not in the books)');
    $ok($eq($ledger->balance($a('mtn')), 50000 + 6000), '2 the counter MTN sale lands on MTN Mobile Money');
    $ok($eq($ledger->balance($a('fapshi')), 18000), '2 app and web payments (incl. the second payment) land on the Fapshi balance');
    $ok($eq($ledger->balance($a('refunds_owed')), 6000), '2 the second payment is owed back on 4191');
    $ok($eq($ledger->balance($a('unearned'), null, ['departure_id' => (string)$d1->id]), 36000), '2 tomorrow trip: 36000 held on 4771, not income yet');
    $ok($eq($ledger->balance($a('sales_counter')), 0) && $eq($ledger->balance($a('sales_online')), 0), '2 no sales before the bus leaves');
    $again = $sync->sync($agency);
    $ok(($again['moves'] ?? 0) === 0, '2 a second sync posts nothing twice');

    // Passenger cancels the app booking: refund 4500 owed; agency cancels a counter ticket: cash back at once.
    $bookings->endBooking(BusBooking::find($app1->id), 'passenger', 4500);
    $bookings->endBooking(BusBooking::find($c2->id), 'agency', 6000);
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('refunds_owed')), 6000 + 4500), '2 passenger refund 4500 owed (4191 = 10500)');
    $ok($eq($ledger->balance($a('counter_cash'), null, ['staff_user_id' => (string)$manager->id]), -6000), '2 the counter refund leaves the cash of whoever gave it (the manager)');
    $ok($eq($ledger->balance($a('unearned'), null, ['departure_id' => (string)$d1->id]), 36000 - 4500 - 6000), '2 both refunds come off what is held for the trip');
    $bookings->markRefundSent($agency, BusBooking::find($app1->id), 'MP123');
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('refunds_owed')), 6000) && $eq($ledger->balance($a('mtn')), 56000 - 4500), '2 refund sent: off 4191, out of MTN (the refunds account)');

    // A paid seat moved to another trip.
    $d2 = $mkDep(today()->addDays(3)->toDateString());
    $c3m = BusBooking::find($c3->id);
    $c3m->update(['departure_id' => $d2->id, 'moved_from_departure_id' => $d1->id]);
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('unearned'), null, ['departure_id' => (string)$d2->id]), 6000) && $eq($ledger->balance($a('unearned'), null, ['departure_id' => (string)$d1->id]), 19500), '2 a moved booking takes its money to the new trip');

    // ---- 3. The bus leaves: what is held becomes sales, per channel, tagged with the trip and bus.
    $d1->update(['status' => 'departed', 'departed_at' => now()->subMinutes(5), 'date' => today()->toDateString(), 'time' => now()->subMinutes(10)->format('H:i:s')]);
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('unearned'), null, ['departure_id' => (string)$d1->id]), 0), '3 trip left: nothing held for it any more');
    $ok($eq($ledger->balance($a('sales_counter')), 12000) && $eq($ledger->balance($a('sales_online')), 1500 + 6000), '3 sales: counter 12000, online 7500 (6000 kept + 1500 kept on the cancelled app ticket)');
    $ok(BusAccLine::where('agency_id', $agency->id)->where('account_id', $a('sales_counter')->id)->where('vehicle_id', $bus->id)->where('departure_id', $d1->id)->exists(), '3 sales lines carry the bus and the trip');
    $ok($rep->health($agency) === [], '3 health: 4771 and agent cash tie out to the tickets (' . implode(' | ', $rep->health($agency)) . ')');
    $rebook = BusMoneyMove::where('agency_id', $agency->id)->where('kind', 'rebook')->first();
    $cashLines = BusAccLine::query()->from('bus_acc_lines as l')->join('bus_acc_accounts as x', 'x.id', '=', 'l.account_id')->where('l.entry_id', $rebook->entry_id)->where('x.class', 5)->count();
    $ok($rebook && $rebook->entry_id && $cashLines === 0, '3 a seat moved to another trip posts nothing to class 5 (no cash event)');
    // The trip runs again (a cancelled trip brought back, a departure undone): sales go back to 4771.
    $d1->update(['status' => 'scheduled', 'departed_at' => null]);
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('sales_counter')), 0) && $eq($ledger->balance($a('unearned'), null, ['departure_id' => (string)$d1->id]), 19500) && !DB::table('bus_acc_recognized')->where('departure_id', $d1->id)->exists(), '3 trip runs again: its sales are mirrored back to 4771');
    $ok($rep->health($agency) === [], '3 health still ties out after the trip runs again');
    $d1->update(['status' => 'departed', 'departed_at' => now()->subMinutes(2)]);
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('sales_counter')), 12000) && $eq($ledger->balance($a('unearned'), null, ['departure_id' => (string)$d1->id]), 0), '3 and when it leaves again its tickets are sales again (once)');
    // A late refund after the trip goes to refunds after the trip, not to 4771.
    $c1m = BusBooking::find($c1->id);
    $bookings->endBooking($c1m, 'passenger', 2000);
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('refunds_given')), -2000), '3 a refund after the trip is a refund given after the trip (7091)');

    // ---- 4. Spending: paid now (tagged to the bus), a supplier bill, paying it, voiding.
    $fuel = $a('fuel');
    $pv = $preview->run($agency, 'expense', fn() => $svc->expense($agency, ['date' => today()->toDateString(), 'account_id' => $fuel->id, 'amount' => 20000, 'paid' => 'now', 'money_account_id' => $a('mtn')->id, 'vehicle_id' => (string)$bus->id], (string)$manager->id));
    $ok($pv['ok'] && count($pv['entries']) === 1 && collect($pv['effects'][0]['rows'] ?? [])->contains(fn($r) => str_starts_with($r[0], '5521')), '4 expense preview: one entry and the MTN balance before/after');
    $ok(!BusAccDocument::where('agency_id', $agency->id)->where('type', 'expense')->exists(), '4 the preview wrote nothing');
    $e1 = $svc->expense($agency, ['date' => today()->toDateString(), 'account_id' => $fuel->id, 'amount' => 20000, 'paid' => 'now', 'money_account_id' => $a('mtn')->id, 'vehicle_id' => (string)$bus->id], (string)$manager->id);
    $ok($e1->number === 'EXP-00001' && $eq($ledger->balance($a('mtn')), 51500 - 20000), '4 fuel paid from MTN (EXP-00001)');
    $pv = $preview->run($agency, 'expense', fn() => $svc->expense($agency, ['date' => today()->toDateString(), 'account_id' => $fuel->id, 'amount' => 20000, 'paid' => 'now', 'money_account_id' => $a('mtn')->id], (string)$manager->id));
    $ok($pv['ok'] && collect($pv['warnings'])->contains(fn($w) => str_contains($w, 'EXP-00001')), '4 the same expense twice is flagged in the preview');
    $repairs = BusAccAccount::where('agency_id', $agency->id)->where('code', '6242')->first();
    $bill = $svc->expense($agency, ['date' => today()->toDateString(), 'account_id' => $repairs->id, 'amount' => 30000, 'paid' => 'later', 'supplier' => 'Garage Nkwen', 'vehicle_id' => (string)$bus->id], (string)$manager->id);
    $ok($eq($bill->owed, 30000) && $eq($ledger->balance($a('suppliers')), 4000 + 30000), '4 a supplier bill: 30000 owed (4011 = 34000 with the opening)');
    $ok($throws(fn() => $svc->payBill($agency, $bill, ['date' => today()->toDateString(), 'amount' => 40000, 'money_account_id' => $a('safe')->id], (string)$manager->id), '30'), '4 paying more than owed is refused');
    $pay = $svc->payBill($agency, $bill, ['date' => today()->toDateString(), 'amount' => 10000, 'money_account_id' => $a('safe')->id], (string)$manager->id);
    $ok($eq($bill->fresh()->owed, 20000) && $eq($ledger->balance($a('safe')), 0), '4 part paid from the safe: 20000 still owed, safe at 0');
    $ok($throws(fn() => $svc->void($agency, $bill->fresh(), 'wrong garage', (string)$manager->id), 'void them first'), '4 a bill with payments cannot be voided');
    $svc->void($agency, $pay, 'paid twice by mistake', (string)$manager->id);
    $ok($eq($bill->fresh()->owed, 30000) && $eq($ledger->balance($a('safe')), 10000), '4 voiding the payment restores the bill and the safe');
    $pv = $preview->run($agency, 'pay', fn() => $svc->payBill($agency, $bill->fresh(), ['date' => today()->toDateString(), 'amount' => 30000, 'money_account_id' => $a('bank')->id], (string)$manager->id));
    $ok($pv['ok'] && collect($pv['warnings'])->contains(fn($w) => str_contains($w, '5211')), '4 paying from an empty bank warns it goes below zero');
    $ok($throws(fn() => $svc->expense($agency, ['date' => today()->toDateString(), 'account_id' => $fuel->id, 'amount' => 500, 'paid' => 'now', 'money_account_id' => $a('counter_cash')->id], (string)$manager->id), 'agent'), '4 spending from counter cash needs the agent');

    // ---- 5. An agent's cash counted: short by 500.
    $held = $svc->agentCash($agency, (string)$agentUser->id);
    $ok($eq($held, 18000), '5 the agent should hold 18000');
    $ok($throws(fn() => $svc->cashCount($agency, ['staff_user_id' => (string)$agentUser->id, 'counted' => 17500, 'to_account_id' => $a('safe')->id], (string)$manager->id), 'short'), '5 a short count needs the reason');
    $pv = $preview->run($agency, 'count', fn() => $svc->cashCount($agency, ['staff_user_id' => (string)$agentUser->id, 'counted' => 17500, 'to_account_id' => $a('safe')->id, 'note' => 'coin missing'], (string)$manager->id));
    $ok($pv['ok'] && collect($pv['warnings'])->contains(fn($w) => str_contains($w, '500')), '5 the count preview says it is short by 500');
    $svc->cashCount($agency, ['staff_user_id' => (string)$agentUser->id, 'counted' => 17500, 'to_account_id' => $a('safe')->id, 'note' => 'coin missing'], (string)$manager->id);
    $ok($eq($svc->agentCash($agency, (string)$agentUser->id), 0) && $eq($ledger->balance($a('cash_short')), 500) && $eq($ledger->balance($a('safe')), 27500), '5 after the count: agent at 0, 500 cash short, 17500 in the safe');

    // ---- 6. Money moved, statement check, other income.
    $svc->transfer($agency, ['date' => today()->toDateString(), 'money_account_id' => $a('safe')->id, 'to_account_id' => $a('bank')->id, 'amount' => 20000, 'fee' => 100], (string)$manager->id);
    $ok($eq($ledger->balance($a('bank')), 20000) && $eq($ledger->balance($a('safe')), 7400) && $eq($ledger->balance($a('charges')), 100), '6 safe to bank 20000 with a 100 charge');
    $ok($throws(fn() => $svc->transfer($agency, ['date' => today()->toDateString(), 'money_account_id' => $a('counter_cash')->id, 'to_account_id' => $a('bank')->id, 'amount' => 100], (string)$manager->id), 'cash count'), '6 counter cash only moves through a cash count');
    $tick = $svc->unmatched($a('bank'), today()->toDateString())->pluck('id')->map(fn($i) => (int)$i)->all();
    $ok($throws(fn() => $svc->check($agency, ['date' => today()->toDateString(), 'account_id' => $a('bank')->id, 'counted' => 19700, 'cleared' => $tick, 'post_as' => 'income'], (string)$manager->id), 'not above'), '6 a check cannot post income when the statement is below');
    $svc->check($agency, ['date' => today()->toDateString(), 'account_id' => $a('bank')->id, 'counted' => 19700, 'cleared' => $tick, 'post_as' => 'charges'], (string)$manager->id);
    $ok($eq($ledger->balance($a('bank')), 19700) && $eq($ledger->balance($a('charges')), 400), '6 statement check posts the 300 bank charges');
    $parcels = BusAccAccount::where('agency_id', $agency->id)->where('code', '7071')->first();
    $svc->income($agency, ['date' => today()->toDateString(), 'account_id' => $parcels->id, 'amount' => 3000, 'money_account_id' => $a('counter_cash')->id, 'staff_user_id' => (string)$agentUser->id, 'vehicle_id' => (string)$bus->id], (string)$manager->id);
    $ok($eq($svc->agentCash($agency, (string)$agentUser->id), 3000), '6 parcel money taken by the agent is in their cash');

    // ---- 7. Journal entries: balanced only, accrual that reverses itself.
    $rent = BusAccAccount::where('agency_id', $agency->id)->where('code', '6222')->first();
    $accr = BusAccAccount::where('agency_id', $agency->id)->where('code', '4081')->first();
    $ok($throws(fn() => $svc->journal($agency, ['date' => today()->toDateString(), 'note' => 'x', 'lines' => [['account_id' => $rent->id, 'debit' => 100], ['account_id' => $accr->id, 'credit' => 90]]], (string)$manager->id), 'equal'), '7 an unbalanced journal entry is refused');
    $ok($throws(fn() => $svc->journal($agency, ['date' => today()->toDateString(), 'note' => 'x', 'lines' => [['account_id' => $a('unearned')->id, 'debit' => 100], ['account_id' => $accr->id, 'credit' => 100]]], (string)$manager->id), '4771'), '7 4771 cannot be moved by hand');
    $ok($throws(fn() => $svc->journal($agency, ['date' => today()->subDays(10)->toDateString(), 'note' => 'x', 'lines' => [['account_id' => $rent->id, 'debit' => 100], ['account_id' => $accr->id, 'credit' => 100]]], (string)$manager->id), 'start'), '7 nothing dated before the books start');
    $j = $svc->journal($agency, ['date' => today()->subDays(2)->toDateString(), 'note' => 'Rent accrual', 'reverse_on' => today()->subDay()->toDateString(), 'lines' => [['account_id' => $rent->id, 'debit' => 15000], ['account_id' => $accr->id, 'credit' => 15000]]], (string)$manager->id);
    $sync->sync($agency);
    $ok($j->fresh()->auto_reverse_entry_id && $eq($ledger->balance($accr), 0) && BusAccEntry::find($j->fresh()->auto_reverse_entry_id)->date->isSameDay(today()->subDay()), '7 the accrual reversed itself on its date');

    // ---- 8. E-Ride statement: owed when made, paid later.
    $st = BusStatement::create(['agency_id' => $agency->id, 'month' => today()->startOfMonth()->toDateString(), 'total' => 3000, 'due_on' => today()->addDays(10)->toDateString(), 'status' => 'due']);
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('eride')), 3000) && $eq($ledger->balance($a('eride_commission')), 3000), '8 the statement makes 3000 commission owed to E-Ride');
    $st->update(['status' => 'paid', 'paid_at' => now()]);
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('eride')), 0), '8 paid: nothing owed to E-Ride');

    // ---- 9. Reports agree with each other.
    $from = $start; $to = today()->toDateString();
    $tb = $rep->trialBalance($agency, $from, $to);
    $ok($tb['balanced'], '9 trial balance: debits = credits');
    $bs = $rep->balanceSheet($agency, $to);
    $ok($bs['balanced'], '9 balance sheet balances (has = owes + owner + result)');
    $is = $rep->incomeStatement($agency, $from, $to);
    $profit = collect($rep->profit($agency, $from, $to, 'bus'));
    $ok($eq($profit->sum('profit'), $is['result']), '9 profit by bus adds up to the income statement result');
    $row = $profit->firstWhere('key', (string)$bus->id);
    $ok($row && $eq($row['fuel'], 20000) && $eq($row['income'], 12000 + 7500 + 3000 - 2000), '9 the bus row: 20500 income (sales, parcels, less the refund after the trip), 20000 fuel');
    $ok($eq($is['result'], $bs['result']), '9 the result is the same in the income statement and the balance sheet');
    $ov = $rep->overview($agency);
    $ok($ov['balanced'] && $ov['pending'] === 0, '9 overview: books balanced, nothing pending');
    $ok($rep->health($agency) === [], '9 health ties out at the end (' . implode(' | ', $rep->health($agency)) . ')');

    // The tie-outs catch what the books did not see (a fare changed behind their back, cash written off the agent).
    $bx = $mkBooking($d2, ['source' => 'counter', 'status' => 'paid', 'payment_method' => 'cash', 'paid_at' => now(), 'staff_user_id' => $agentUser->id, 'amount' => 4000]);
    $sync->sync($agency);
    $ok($rep->health($agency) === [], '9 health clean before tampering');
    DB::table('bus_bookings')->where('id', $bx->id)->update(['amount' => 4500]);
    $h = $rep->health($agency);
    $ok(count($h) === 2 && str_contains($h[0], 'by its tickets') && str_contains($h[1], 'Counter cash'), '9 a fare changed outside the books shows on 4771 and on the agent cash (' . implode(' | ', $h) . ')');
    DB::table('bus_bookings')->where('id', $bx->id)->update(['amount' => 4000]);
    $ok($rep->health($agency) === [], '9 and clears when it agrees again');
    // ---- 10. Closing a month and what happens around it.
    $ok($throws(fn() => $svc->closeMonth($agency, today()->startOfMonth()->toDateString(), (string)$manager->id), 'once it is over'), '10 the current month cannot close yet');
    // Move the start back to last month to test a closed month.
    $books->update(['starts_on' => today()->subMonthNoOverflow()->startOfMonth()->addDays(5)->toDateString()]);
    $last = today()->subMonthNoOverflow()->startOfMonth();
    $svc->closeMonth($agency, $last->toDateString(), (string)$manager->id);
    $ok($ledger->monthClosed((string)$agency->id, $last), '10 last month closed');
    $ok($throws(fn() => $svc->expense($agency, ['date' => $last->copy()->addDays(10)->toDateString(), 'account_id' => $fuel->id, 'amount' => 100, 'paid' => 'now', 'money_account_id' => $a('mtn')->id], (string)$manager->id), 'closed'), '10 nothing by hand in a closed month');
    BusMoneyMove::create(['agency_id' => $agency->id, 'booking_id' => $c1->id, 'departure_id' => $d1->id, 'kind' => 'refund_sent', 'channel' => 'counter', 'amount' => 100, 'at' => $last->copy()->addDays(10)]);
    $sync->sync($agency);
    $late = BusMoneyMove::where('agency_id', $agency->id)->where('amount', 100)->where('kind', 'refund_sent')->first();
    $ok($late->entry_id && BusAccEntry::find($late->entry_id)->date->isToday(), '10 an automatic posting for a closed month lands today');
    $ok($throws(fn() => $svc->reopenMonth($agency, $last->toDateString(), 'no', (string)$manager->id), 'why'), '10 reopening needs a reason');
    $svc->reopenMonth($agency, $last->toDateString(), 'Correct a fuel receipt', (string)$manager->id);
    $ok(!$ledger->monthClosed((string)$agency->id, $last) && DB::table('bus_acc_months')->where('agency_id', $agency->id)->value('reopen_reason') === 'Correct a fuel receipt', '10 reopened, with the reason kept');

    // ---- 11. The ledger's own guards.
    $dup = false; try { $ledger->post($agency, ['date' => today(), 'memo' => 'x', 'lines' => [[$fuel, 10, 0], [$a('mtn'), 0, 10]], 'source_key' => 'doc:' . $e1->id]); } catch (Illuminate\Database\UniqueConstraintViolationException) { $dup = true; }
    $ok($dup, '11 one posting per source event');
    $ok($throws(fn() => $ledger->post($agency, ['date' => today()->addDay(), 'memo' => 'x', 'lines' => [[$fuel, 10, 0], [$a('mtn'), 0, 10]]]), 'future'), '11 no future dates');
    $other = BusAccAccount::where('agency_id', '!=', $agency->id)->first();
    if ($other) { $ok($throws(fn() => $ledger->post($agency, ['date' => today(), 'memo' => 'x', 'lines' => [[$other, 10, 0], [$a('mtn'), 0, 10]]]), 'branch'), '11 another branch account is refused'); }
    $e = BusAccEntry::find($e1->entry_id);
    $ledger->reverse($e, 'test');
    $ok($throws(fn() => $ledger->reverse($e->fresh(), 'test'), 'already reversed'), '11 an entry is reversed once');
    $tb = $rep->trialBalance($agency, '2000-01-01', today()->toDateString());
    $ok($tb['balanced'], '11 still balanced after everything');

    // ---- 12. Fapshi keeps 3% on an app payment: the fee is a charge, the ticket stays at full price.
    $wb = $mkBooking($d2, ['source' => 'web']);
    $fp = BusPayment::create(['booking_id' => $wb->id, 'agency_id' => $agency->id, 'environment' => 'test', 'external_id' => 'acc1-' . Str::random(8), 'amount' => 6000,
        'status' => 'successful', 'paid_at' => now(), 'raw_response' => ['payment-status' => ['amount' => 6000, 'revenue' => 5820]]]);
    $fapBefore = $ledger->balance($a('fapshi')); $chBefore = $ledger->balance($a('charges')); $heldBefore = $ledger->balance($a('unearned'), null, ['departure_id' => (string)$d2->id]); $healthBefore = $rep->health($agency);
    $bookings->settlePaid(BusBooking::find($wb->id), $fp);
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('fapshi')) - $fapBefore, 5820) && $eq($ledger->balance($a('charges')) - $chBefore, 180), '12 app payment of 6000: Fapshi up 5820, 180 fee charged');
    $ok($eq($ledger->balance($a('unearned'), null, ['departure_id' => (string)$d2->id]) - $heldBefore, 6000), '12 the ticket is held at its full price');
    $sync->sync($agency);
    $ok(BusAccEntry::where('agency_id', $agency->id)->where('source_type', 'fee')->count() === 1, '12 the fee posts once');
    // (Section 10 moved the start date back a month, which real books never do: older sales then look missing. The fee must add nothing new.)
    $ok($rep->health($agency) === $healthBefore, '12 the fee adds no health problem');

    // ---- 13. An agency without a head office carries its own buses; their wear posts each month.
    $books->update(['starts_on' => today()->startOfMonth()->toDateString()]);
    DB::table('bus_acc_months')->where('agency_id', $agency->id)->delete();
    $assetDoc = $svc->addAsset($agency, ['date' => today()->toDateString(), 'vehicle_id' => (string)$bus->id, 'value' => 12000000, 'years' => 4, 'end_value' => 0, 'funded_by' => 'owner'], (string)$manager->id);
    $ok($assetDoc->number === 'BUS-00001' && $eq($ledger->balance($a('buses')), 12000000), '13 the bus is in the branch books at 12M');
    Illuminate\Support\Carbon::setTestNow(today()->addMonthNoOverflow()->startOfMonth()->setTime(8, 0));
    $sync->sync($agency);
    $ok($eq($ledger->balance($a('depreciation')), 250000) && $eq(-$ledger->balance($a('bus_wear')), 250000), '13 next month: 250 000 of wear (12M over 48 months), on the branch books');
    $row = collect($rep->profit($agency, today()->subMonthNoOverflow()->startOfMonth()->toDateString(), today()->toDateString(), 'bus'))->firstWhere('key', (string)$bus->id);
    $ok($row && $row['other'] >= 250000, '13 profit per bus counts the wear');
    $ok($throws(fn() => $svc->closeYear($agency, today()->year, (string)$manager->id), 'over'), '13 the current year cannot close');
    $ok($rep->trialBalance($agency, '2000-01-01', today()->toDateString())['balanced'], '13 still balanced');
    Illuminate\Support\Carbon::setTestNow();
} catch (Throwable $e) {
    $fail++;
    echo "ERROR ", get_class($e), ': ', $e->getMessage(), ' @ ', $e->getFile(), ':', $e->getLine(), "\n";
    if ($e instanceof ValidationException) { echo json_encode($e->errors()), "\n"; }
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
