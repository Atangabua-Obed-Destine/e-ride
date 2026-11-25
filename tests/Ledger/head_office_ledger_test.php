<?php
/*
 * A bus company's head office books in the accounting module (E-Ride: App\Ledger\ERide\HeadOffice
 * and the combined statements), end to end through the real routes, rolled back:
 * ticket money the company collects for a branch, E-Ride's statement the head office pays,
 * both sides of each liaison, the combined statements cancelling them, and who may see what.
 * Run: php tests/Ledger/head_office_ledger_test.php
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\JournalEntryLine;
use App\Ledger\Services\LedgerSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAgency, BusCompany, BusCompanyStaff, BusDeparture, BusDestination, BusMoneyMove, BusStaff, BusStatement, BusVehicle};
use Modules\UserManagement\Entities\User;

$pass = 0; $fail = 0; $fails = [];
$ok = function ($c, $label) use (&$pass, &$fail, &$fails) { if ($c) { $pass++; } else { $fail++; $fails[] = $label; } print(($c ? 'PASS ' : 'FAIL ') . $label . "\n"); };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;

DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    \App\Ledger\LedgerObserver::forget();
    $director = accTestUser('HO Director'); $regional = accTestUser('HO Regional'); $mgrA = accTestUser('HO Branch A'); $mgrB = accTestUser('HO Branch B');
    $slug = 'ho-led-' . Str::lower(Str::random(6));
    BusCompany::forceCreate(['name' => 'HO Ledger Company', 'slug' => $slug, 'status' => 'active', 'money_mode' => 'company', 'bill_mode' => 'company',
        'webhook_code' => Str::lower(Str::random(16)), 'turnaround_minutes' => 60]);
    $company = BusCompany::where('slug', $slug)->first();
    BusCompanyStaff::create(['company_id' => $company->id, 'user_id' => $director->id, 'role' => 'director', 'agency_ids' => [], 'active' => true]);
    BusCompanyStaff::create(['company_id' => $company->id, 'user_id' => $regional->id, 'role' => 'regional', 'agency_ids' => [], 'active' => true]);
    $mk = function (string $name, string $city, User $mgr) use ($company) {
        BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => $name, 'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)), 'origin_city' => $city,
            'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'company_id' => $company->id, 'branch_label' => $city, 'rules' => []]);
        $a = BusAgency::where('name', $name)->first();
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
        BusDestination::forceCreate(['agency_id' => $a->id, 'city' => 'Douala', 'active' => true, 'duration_minutes' => 360]);
        $dest = BusDestination::where('agency_id', $a->id)->first();
        BusVehicle::forceCreate(['agency_id' => $a->id, 'company_id' => $company->id, 'registration' => 'HO ' . strtoupper(Str::random(3)), 'name' => 'Coach', 'class' => 'classic', 'status' => 'ready', 'total_seats' => 10]);
        BusDeparture::forceCreate(['agency_id' => $a->id, 'destination_id' => $dest->id, 'class' => 'classic', 'kind' => 'fixed', 'date' => today()->addDay()->toDateString(),
            'time' => '09:00:00', 'seat_plan' => ['seats' => ['1', '2']], 'capacity' => 2, 'status' => 'scheduled']);
        return [$a, BusDeparture::where('agency_id', $a->id)->first()];
    };
    [$A, $depA] = $mk('HO Ledger Bamenda', 'Bamenda', $mgrA);
    [$B] = $mk('HO Ledger Buea', 'Buea', $mgrB);

    $session = $app['session']->driver();
    $call = function (string $method, string $url, array $data = [], ?User $as = null) use ($app, $kernel, $session) {
        $req = Request::create($url, $method, $data, [], [], ['HTTP_ACCEPT' => 'text/html']);
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
    $setup = function (string $portalUrl, User $as) use ($call) {
        $call('GET', $portalUrl, [], $as);
        $call('POST', '/partner/accounting/setup', ['start' => 'today', 'reviewed' => 1], $as);
    };
    $setup('/partner/bus-agency/accounting', $mgrA);
    $setup('/partner/bus-agency/accounting', $mgrB);
    $setup('/partner/bus-company/accounting', $director);
    $bid = fn(string $type, string $id) => (int)DB::table('ledger_businesses')->where('owner_type', $type)->where('owner_id', $id)->value('id');
    $hoBid = $bid('bus_company', (string)$company->id); $aBid = $bid('bus_agency', (string)$A->id); $bBid = $bid('bus_agency', (string)$B->id);
    $ok($hoBid && $aBid && $bBid && Ledger::isSetUp($hoBid) && Ledger::isSetUp($aBid), 'head office and both branches have books set up');
    \App\Ledger\LedgerObserver::forget();

    $bal = function (int $books, string $codeLike) {
        return round((float)Ledger::forBusiness($books, fn() => JournalEntryLine::whereHas('journalEntry', fn($q) => $q->where('is_posted', true))
            ->whereIn('account_id', ChartOfAccount::where('account_code', 'like', $codeLike)->pluck('id'))->selectRaw('COALESCE(SUM(debit - credit), 0) as b')->value('b')), 2);
    };

    // ---- 1. Ticket money collected on the company's account for branch A
    BusMoneyMove::create(['agency_id' => $A->id, 'booking_id' => (string)Str::uuid(), 'departure_id' => (string)$depA->id, 'kind' => 'sale',
        'channel' => 'app', 'method' => 'company', 'amount' => 6000, 'at' => now()]);
    LedgerSync::flush();
    $ok($eq($bal($aBid, '1811'), 6000) && $eq($bal($aBid, '477'), -6000), 'branch A: Dr 1811 head office / Cr 477 held for the trip');
    $ok($eq($bal($hoBid, '5523'), 6000) && $eq($bal($hoBid, '182%'), -6000), 'head office: Dr 5523 Fapshi / Cr 182x owed to branch A');
    $liaisonA = Ledger::forBusiness($hoBid, fn() => ChartOfAccount::where('account_code', 'like', '182%')->first());
    $ok($liaisonA && str_contains($liaisonA->account_name, 'Bamenda'), 'the head office liaison account is named after the branch (' . ($liaisonA?->account_code . ' ' . $liaisonA?->account_name) . ')');

    // ---- 2. E-Ride's statement of branch B paid by the head office
    $csId = (string)Str::uuid();
    DB::table('bus_company_statements')->insert(['id' => $csId, 'company_id' => $company->id, 'month' => now()->startOfMonth()->toDateString(), 'total' => 1500,
        'status' => 'paid', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $st = BusStatement::forceCreate(['agency_id' => $B->id, 'company_statement_id' => $csId, 'month' => now()->startOfMonth()->toDateString(),
        'total' => 1500, 'due_on' => now()->addDays(10)->toDateString(), 'status' => 'paid', 'paid_at' => now()]);
    LedgerSync::flush();
    $ok($eq($bal($bBid, '401'), 0) && $eq($bal($bBid, '1811'), -1500) && $eq($bal($bBid, '632'), 1500), 'branch B: commission 632 / 401, then paid by the head office: Dr 401 / Cr 1811');
    $ok($eq($bal($hoBid, '182%'), -6000 + 1500), 'head office: Dr 182x branch B / Cr the money it paid from');

    // ---- 3. The sweep finds nothing more; combined statements cancel the liaison
    $stats = Ledger::forBusiness($hoBid, fn() => (new LedgerSync())->sweep());
    $ok(empty($stats['posted'] ?? 0) && empty($stats['failed'] ?? 0), 'head office sweep: nothing left (' . json_encode($stats) . ')');
    $sumLiaison = $bal($hoBid, '18%') + $bal($aBid, '18%') + $bal($bBid, '18%');
    $ok($eq($sumLiaison, 0), 'liaison accounts cancel across the three sets of books');
    $r = $call('GET', '/partner/bus-company/accounting', [], $director);
    $r = $call('GET', '/partner/accounting/combined', [], $director);
    $html = $r->getContent();
    $ok($r->getStatusCode() === 200 && str_contains($html, 'Bamenda') && str_contains($html, 'Buea'), 'combined statements open, branches side by side (' . $r->getStatusCode() . ')');
    $ok(!str_contains($html, 'a movement booked on one side only'), 'combined: the liaison nets to 0');
    $ok(str_contains($call('GET', '/partner/accounting/general-ledger/trial-balance', [], $director)->getContent(), 'ledger-nav'), "head office trial balance opens on its own books");

    // ---- 4. Who may see what
    $call('GET', '/partner/bus-agency/accounting', [], $mgrA);
    $r = $call('GET', '/partner/accounting/combined', [], $mgrA);
    $ok($r->getStatusCode() === 404, 'a branch manager has no combined statements (' . $r->getStatusCode() . ')');
    $session->forget('ledger.owner');
    $r = $call('GET', '/partner/bus-company/accounting', [], $regional);
    $r2 = $call('GET', '/partner/accounting/combined', [], $regional);
    $ok($r2->getStatusCode() !== 200, 'a regional manager cannot open the head office books (' . $r2->getStatusCode() . ')');
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
