<?php
/*
 * Fixed assets and depreciation on a bus branch's books (paxhitestbed's fixed asset screens as ported
 * to posinnova, routes/ledger/assets.php), end to end through the real routes, rolled back:
 * the Buses category made at setup, a bus of the fleet registered as an asset, its purchase posted
 * (Dr 245 / Cr the payment account, or 401 when owed), a month of wear calculated and posted
 * (Dr 681 / Cr 284, lines tagged with the bus), the reports, a disposal through 812, another branch
 * never seeing the assets, who may do what, and the menu. Last, what a head office's books do with
 * a company bus (reported, not required).
 *
 * Run: php tests/Ledger/fixed_assets_test.php
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use App\Ledger\Ledger;
use App\Ledger\Models\{ChartOfAccount, DepreciationSchedule, FixedAsset, FixedAssetCategory, JournalEntry, JournalEntryLine, PaymentAccount};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAgency, BusCompany, BusCompanyStaff, BusStaff, BusVehicle};
use Modules\UserManagement\Entities\User;

$pass = 0; $fail = 0; $fails = [];
$ok = function ($c, $label) use (&$pass, &$fail, &$fails) { if ($c) { $pass++; } else { $fail++; $fails[] = $label; } print(($c ? 'PASS ' : 'FAIL ') . $label . "\n"); };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;

DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    \App\Ledger\LedgerObserver::forget();
    $mgrA = accTestUser('Assets Manager A'); $accA = accTestUser('Assets Accountant A'); $agentA = accTestUser('Assets Agent A'); $mgrB = accTestUser('Assets Manager B');

    $mk = function (string $name, User $mgr, array $more = []) {
        BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => $name, 'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)), 'origin_city' => 'Bamenda',
            'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => []] + $more);
        $a = BusAgency::where('name', $name)->first();
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
        BusVehicle::forceCreate(['agency_id' => $a->id, 'company_id' => $more['company_id'] ?? null, 'registration' => 'FA ' . strtoupper(Str::random(4)), 'name' => 'Coach',
            'class' => 'classic', 'status' => 'ready', 'total_seats' => 30]);
        return [$a, BusVehicle::where('agency_id', $a->id)->first()];
    };
    [$A, $busA] = $mk('Assets Branch Bamenda', $mgrA);
    [$B, $busB] = $mk('Assets Branch Kumba', $mgrB);
    BusStaff::create(['agency_id' => $A->id, 'user_id' => $accA->id, 'role' => 'accountant', 'active' => true]);
    BusStaff::create(['agency_id' => $A->id, 'user_id' => $agentA->id, 'role' => 'counter', 'active' => true]);

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
    $to = fn($res) => (string)$res->headers->get('Location');
    $flash = fn() => (string)($session->get('status')['msg'] ?? '');
    $errs = function () use ($session) {
        $e = $session->get('errors');
        return $e ? implode(' | ', $e->all()) : '';
    };
    $P = '/partner/accounting/';

    // ---- 1. Branch A's books, and the Buses category made at setup.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrA);
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrA);
    $bidA = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $A->id)->value('id');
    $ok($bidA > 0 && Ledger::isSetUp($bidA), 'branch A books opened and set up');
    $in = fn(callable $fn, ?int $b = null) => Ledger::forBusiness($b ?? $bidA, $fn);
    $acc = fn(string $code, ?int $b = null) => (int)$in(fn() => ChartOfAccount::where('account_code', $code)->value('id'), $b);
    $bal = fn(string $code, ?int $b = null) => round((float)$in(fn() => JournalEntryLine::where('account_id', ChartOfAccount::where('account_code', $code)->value('id'))
        ->whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->selectRaw('COALESCE(SUM(debit - credit), 0) as b')->value('b'), $b), 2);
    $linesOf = fn(int $entryId, ?int $b = null) => $in(fn() => JournalEntryLine::with('account')->where('journal_entry_id', $entryId)->get(), $b);
    $codeOf = fn($line) => (string)$line->account?->account_code;

    $busCat = $in(fn() => FixedAssetCategory::where('code', 'BUS')->first());
    $ok($busCat && $busCat->asset_account_id === $acc('245') && $busCat->accumulated_depreciation_account_id === $acc('284')
        && $busCat->depreciation_account_id === $acc('681') && $busCat->disposal_account_id === $acc('812'), 'setup made the Buses category on 245 / 284 / 681 / 812');
    $r = $call('GET', $P . 'fixed-asset-categories', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Buses and coaches'), 'Categories page opens and lists Buses and coaches (' . $r->getStatusCode() . ' ' . $to($r) . ')');
    $ok($call('GET', $P . 'fixed-asset-categories/create', [], $mgrA)->getStatusCode() === 200, 'new category form opens');
    $call('POST', $P . 'fixed-asset-categories', ['code' => 'OFF', 'name' => 'Office equipment', 'depreciation_method' => 'straight_line', 'default_useful_life' => 5,
        'asset_account_id' => $acc('244'), 'accumulated_depreciation_account_id' => $acc('284'), 'depreciation_account_id' => $acc('681')], $mgrA);
    $offCat = $in(fn() => FixedAssetCategory::where('code', 'OFF')->first());
    $ok((bool)$offCat, 'a second category (Office equipment) is saved from its form ' . $errs());
    $ok($call('GET', $P . 'fixed-asset-categories/' . $busCat->id . '/edit', [], $mgrA)->getStatusCode() === 200, 'category edit form opens');

    // ---- 2. A payment account to pay from.
    $call('GET', $P . 'payment-account', [], $mgrA);
    $cashType = $in(fn() => \App\Ledger\Models\PaymentAccountType::where('kind', 'cash')->value('id'));
    $call('POST', $P . 'payment-account/store', ['title' => 'Head cash box', 'account_type_id' => $cashType, 'chart_of_account_id' => $acc('571'), 'opening_balance' => 0, 'reviewed' => 1], $mgrA);
    $payId = (int)$in(fn() => PaymentAccount::where('title', 'Head cash box')->value('id'));
    $ok($payId > 0, 'payment account Head cash box (571) created');

    // ---- 3. Register a bus of the fleet as an asset.
    $r = $call('GET', $P . 'fixed-assets/create', [], $mgrA);
    $html = $r->getContent();
    $ok($r->getStatusCode() === 200 && str_contains($html, 'Bus of the fleet') && str_contains($html, $busA->registration), 'Add fixed asset form opens with Bus of the fleet listing the branch bus (' . $r->getStatusCode() . ')');
    $ok(!str_contains($html, $busB->registration), 'the other branch\'s bus is not offered');
    $today = today()->toDateString();
    $asset = ['name' => 'Coach 30 seats', 'category_id' => $busCat->id, 'vehicle_id' => (string)$busA->id, 'acquisition_date' => $today,
        'acquisition_cost' => 36000000, 'salvage_value' => 3600000, 'useful_life_years' => 10, 'depreciation_method' => 'straight_line', 'vendor' => 'Bamenda Motors'];
    $r = $call('POST', $P . 'fixed-assets', array_merge($asset, ['vehicle_id' => (string)$busB->id]), $mgrA);
    $ok(!$in(fn() => FixedAsset::exists()), 'another branch\'s bus is refused');
    $r = $call('POST', $P . 'fixed-assets', $asset, $mgrA);
    $fa = $in(fn() => FixedAsset::where('vehicle_id', (string)$busA->id)->first());
    $ok($fa && $fa->useful_life_months === 120 && $eq($fa->book_value, 36000000) && $fa->status === 'active' && str_starts_with($fa->asset_code, 'FA-')
        && $fa->created_by === (string)$mgrA->id, 'the bus is registered (code ' . ($fa->asset_code ?? '-') . ', 120 months, created_by the UUID) ' . $errs() . $flash());
    $r = $call('GET', $P . 'fixed-assets/' . $fa->id, [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), $fa->asset_code) && str_contains($r->getContent(), $busA->registration), 'asset page opens and names the bus (' . $r->getStatusCode() . ')');
    $ok($call('GET', $P . 'fixed-assets/' . $fa->id . '/edit', [], $mgrA)->getStatusCode() === 200, 'asset edit form opens');
    $r = $call('GET', $P . 'fixed-assets', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), $fa->asset_code) && str_contains($r->getContent(), $busA->registration), 'Fixed assets list opens and shows the bus');

    // ---- 4. Post the purchase (review dialog).
    $call('POST', $P . 'fixed-assets/' . $fa->id . '/post-purchase', ['paid_from' => $payId], $mgrA);
    $ok(!$in(fn() => FixedAsset::find($fa->id)->purchase_journal_entry_id), 'purchase without the review tick is refused');
    $call('POST', $P . 'fixed-assets/' . $fa->id . '/post-purchase', ['paid_from' => $payId, 'reviewed' => 1], $mgrA);
    $fa = $in(fn() => FixedAsset::find($fa->id));
    $lines = $fa->purchase_journal_entry_id ? $linesOf($fa->purchase_journal_entry_id) : collect();
    $dr = $lines->first(fn($l) => $codeOf($l) === '245');
    $cr = $lines->first(fn($l) => $codeOf($l) === '571');
    $ok($dr && $cr && $eq($dr->debit, 36000000) && $eq($cr->credit, 36000000) && $lines->count() === 2, 'purchase posted: Dr 245 / Cr 571 Head cash box, 36 000 000 ' . $flash());
    $ok($dr && (string)$dr->vehicle_id === (string)$busA->id, 'the 245 line carries the bus');
    $call('POST', $P . 'fixed-assets/' . $fa->id . '/post-purchase', ['paid_from' => $payId, 'reviewed' => 1], $mgrA);
    $ok($in(fn() => JournalEntry::where('reference_type', 'fixed_asset')->count()) === 1, 'the purchase cannot be posted twice');

    // a second asset, owed to the supplier
    $call('POST', $P . 'fixed-assets', ['name' => 'Office laptop', 'category_id' => $offCat->id, 'acquisition_date' => $today, 'acquisition_cost' => 600000,
        'useful_life_years' => 5, 'depreciation_method' => 'straight_line'], $mgrA);
    $laptop = $in(fn() => FixedAsset::where('name', 'Office laptop')->first());
    $call('POST', $P . 'fixed-assets/' . $laptop->id . '/post-purchase', ['paid_from' => 'supplier', 'reviewed' => 1], $mgrA);
    $laptop = $in(fn() => FixedAsset::find($laptop->id));
    $ok($laptop->purchase_journal_entry_id && $eq($bal('401'), -600000) && $eq($bal('244'), 600000), 'an asset still owed: Dr 244 / Cr 401 Suppliers');

    // ---- 5. A month of wear: calculate, then post through the review.
    $month = now()->format('Y-m');
    $call('POST', $P . 'fixed-assets/calculate-depreciation', ['month' => $month], $mgrA);
    $sched = $in(fn() => DepreciationSchedule::where('fixed_asset_id', $fa->id)->first());
    $ok($sched && $sched->status === 'pending' && $eq($sched->depreciation_amount, 270000), 'calculate: the bus month pending at 270 000 ((36M - 3.6M) / 120) ' . $flash());
    $call('POST', $P . 'fixed-assets/post-depreciation', ['month' => $month], $mgrA);
    $ok($in(fn() => DepreciationSchedule::find($sched->id)->status) === 'pending', 'posting the month without the review tick is refused');
    $call('POST', $P . 'fixed-assets/post-depreciation', ['month' => $month, 'reviewed' => 1], $mgrA);
    $sched = $in(fn() => DepreciationSchedule::find($sched->id));
    $ok($sched->status === 'posted' && $sched->journal_entry_id, 'the month is posted ' . $flash());
    $lines = $sched->journal_entry_id ? $linesOf($sched->journal_entry_id) : collect();
    $d681 = $lines->first(fn($l) => $codeOf($l) === '681');
    $c284 = $lines->first(fn($l) => $codeOf($l) === '284');
    $ok($d681 && $c284 && $eq($d681->debit, 270000) && $eq($c284->credit, 270000), 'depreciation entry: Dr 681 / Cr 284, 270 000');
    $ok($d681 && $c284 && (string)$d681->vehicle_id === (string)$busA->id && (string)$c284->vehicle_id === (string)$busA->id, 'both depreciation lines carry the bus (vehicle_id)');
    $fa = $in(fn() => FixedAsset::find($fa->id));
    $ok($eq($fa->accumulated_depreciation, 270000) && $eq($fa->book_value, 35730000), 'the bus: accumulated 270 000, book value 35 730 000');
    $ok($in(fn() => DepreciationSchedule::where('fixed_asset_id', $laptop->id)->where('status', 'posted')->exists()), 'the laptop\'s month posts with it (10 000)');
    $call('POST', $P . 'fixed-assets/post-depreciation', ['month' => $month, 'reviewed' => 1], $mgrA);
    $ok($in(fn() => DepreciationSchedule::where('fixed_asset_id', $fa->id)->count()) === 1 && $eq($bal('681'), 280000), 'running the month again charges nothing more');

    // ---- 6. Reports.
    $r = $call('GET', $P . 'fixed-assets-register', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), $fa->asset_code) && str_contains($r->getContent(), $busA->registration), 'Asset report (register) opens and shows the bus (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'fixed-assets-depreciation-report', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), $fa->asset_code), 'Depreciation report opens and shows the bus month (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'fixed-assets-depreciation-summary', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Buses and coaches'), 'Depreciation summary opens with the Buses category (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'fixed-assets-register', ['export' => 'excel'], $mgrA);
    $ok($r->getStatusCode() === 200, 'Asset report exports to Excel (' . $r->getStatusCode() . ')');
    $r = $call('GET', $P . 'general-ledger/trial-balance', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'trial balance still opens');

    // ---- 7. Menu: the four entries, active on their pages.
    $html = $call('GET', $P . 'fixed-assets/' . $fa->id, [], $mgrA)->getContent();
    foreach (['ledger.fixed-assets.index', 'ledger.fixed-asset-categories.index', 'ledger.fixed-assets.register', 'ledger.fixed-assets.depreciation-report'] as $rn) {
        $ok(\App\Ledger\ERide\Screens::isReady($rn) && str_contains($html, 'href="' . route($rn) . '"'), "menu has $rn");
    }
    $active = fn(string $page, string $rn) => str_contains($call('GET', $P . $page, [], $mgrA)->getContent(), 'href="' . route($rn) . '" class="active"');
    $ok($active('fixed-assets/' . $fa->id, 'ledger.fixed-assets.index') && $active('fixed-assets/create', 'ledger.fixed-assets.index'), 'Asset Register is the active entry on the asset page and the form');
    $ok(!$active('fixed-assets-register', 'ledger.fixed-assets.index') && $active('fixed-assets-register', 'ledger.fixed-assets.register'), 'on the Asset Report, only Asset Report is active');
    $ok($active('fixed-assets-depreciation-summary', 'ledger.fixed-assets.depreciation-report') && $active('fixed-asset-categories/' . $busCat->id . '/edit', 'ledger.fixed-asset-categories.index'),
        'Depreciation Report is active on the summary, Categories on a category form');
    $ok(!\App\Ledger\ERide\Screens::isPending('ledger.fixed-assets.create') && !\App\Ledger\ERide\Screens::isPending('ledger.fixed-assets.depreciation-summary'), 'the asset pages are not "coming soon"');

    // ---- 8. Dispose of the bus: sold for 30 000 000 into the cash box.
    $call('POST', $P . 'fixed-assets/' . $fa->id . '/dispose', ['disposal_date' => $today, 'disposal_method' => 'sale', 'disposal_proceeds' => 30000000, 'payment_account_id' => $payId], $mgrA);
    $ok($in(fn() => FixedAsset::find($fa->id)->status) === 'active', 'disposal without the review tick is refused');
    $call('POST', $P . 'fixed-assets/' . $fa->id . '/dispose', ['disposal_date' => $today, 'disposal_method' => 'sale', 'disposal_proceeds' => 30000000,
        'payment_account_id' => $payId, 'notes' => 'Sold to a transporter', 'reviewed' => 1], $mgrA);
    $fa = $in(fn() => FixedAsset::find($fa->id));
    $ok($fa->status === 'disposed' && $fa->disposal_journal_entry_id, 'the bus is disposed ' . $flash());
    $lines = $fa->disposal_journal_entry_id ? $linesOf($fa->disposal_journal_entry_id) : collect();
    $sum = fn(string $code, string $side) => round((float)$lines->filter(fn($l) => $codeOf($l) === $code)->sum($side), 2);
    $ok($eq($lines->sum('debit'), $lines->sum('credit')) && $eq($lines->sum('debit'), 36000000), 'disposal entry balanced (36 000 000 each side)');
    $ok($eq($sum('284', 'debit'), 270000) && $eq($sum('571', 'debit'), 30000000) && $eq($sum('245', 'credit'), 36000000) && $eq($sum('812', 'debit'), 5730000),
        'disposal: Dr 284 270 000, Dr 571 30 000 000, Cr 245 36 000 000, Dr 812 loss 5 730 000 (the category\'s account)');
    $ok($lines->isNotEmpty() && $lines->every(fn($l) => (string)$l->vehicle_id === (string)$busA->id), 'every disposal line carries the bus');
    $ok($eq($bal('245'), 0) && $eq($bal('284'), -10000), 'after the sale 245 is back to 0 and 284 holds only the laptop');
    $r = $call('GET', $P . 'fixed-assets/' . $fa->id . '/edit', [], $mgrA);
    $ok($r->getStatusCode() === 302, 'a disposed asset cannot be edited');

    // ---- 9. Who may do what.
    $call('GET', '/partner/bus-agency/accounting', [], $accA);
    $r = $call('GET', $P . 'fixed-assets', [], $accA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Office laptop'), 'the branch accountant opens the asset list (' . $r->getStatusCode() . ')');
    $call('POST', $P . 'fixed-assets', ['name' => 'Ticket printer', 'category_id' => $offCat->id, 'acquisition_date' => $today, 'acquisition_cost' => 150000,
        'useful_life_years' => 3, 'depreciation_method' => 'straight_line'], $accA);
    $printer = $in(fn() => FixedAsset::where('name', 'Ticket printer')->first());
    $ok($printer && $printer->created_by === (string)$accA->id, 'the accountant registers an asset');
    $ok(\App\Ledger\ERide\Books::current()['role'] === 'accountant'
        && \App\Ledger\ERide\Books::allows('fixed-asset-edit') === true && \App\Ledger\ERide\Books::allows('fixed-asset-delete') === true
        && \App\Ledger\ERide\Books::allows('accounting-period-reopen') === false, 'accountant: every fixed-asset right (none is manager-only), but no reopening');
    $call('DELETE', $P . 'fixed-assets/' . $printer->id, [], $accA);
    $ok(!$in(fn() => FixedAsset::find($printer->id)), 'an asset never posted can be deleted');
    $session->forget('ledger.owner');
    $call('GET', '/partner/bus-agency/accounting', [], $agentA);
    $r = $call('GET', $P . 'fixed-assets', [], $agentA);
    $ok($r->getStatusCode() === 302 && !str_contains($to($r), 'fixed-assets'), 'a counter agent cannot open the assets (' . $r->getStatusCode() . ')');

    // ---- 10. Branch B never sees branch A's assets.
    $session->forget('ledger.owner');
    $call('GET', '/partner/bus-agency/accounting', [], $mgrB);
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrB);
    $bidB = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $B->id)->value('id');
    $ok($bidB && $bidB !== $bidA && Ledger::isSetUp($bidB), 'branch B books set up');
    $r = $call('GET', $P . 'fixed-assets', [], $mgrB);
    $ok($r->getStatusCode() === 200 && !str_contains($r->getContent(), 'Office laptop') && !str_contains($r->getContent(), $fa->asset_code), 'branch B\'s list does not show A\'s assets');
    $r = $call('GET', $P . 'fixed-assets-register', [], $mgrB);
    $ok($r->getStatusCode() === 200 && !str_contains($r->getContent(), 'Office laptop'), 'nor does its asset report');
    $ok($call('GET', $P . 'fixed-assets/' . $laptop->id, [], $mgrB)->getStatusCode() === 404, 'A\'s asset page is 404 for B');
    $call('POST', $P . 'fixed-assets/' . $laptop->id . '/dispose', ['disposal_date' => $today, 'disposal_method' => 'scrap', 'reviewed' => 1], $mgrB);
    $ok($in(fn() => FixedAsset::find($laptop->id)->status) === 'active', 'B cannot dispose of A\'s asset');
    $call('POST', $P . 'fixed-assets', ['name' => 'Wrong category', 'category_id' => $offCat->id, 'acquisition_date' => $today, 'acquisition_cost' => 1000,
        'useful_life_years' => 3, 'depreciation_method' => 'straight_line'], $mgrB);
    $ok(!$in(fn() => FixedAsset::where('name', 'Wrong category')->exists(), $bidB), 'B cannot use A\'s category');
    $html = $call('GET', $P . 'fixed-asset-categories', [], $mgrB)->getContent();
    $ok(str_contains($html, 'Buses and coaches') && !str_contains($html, 'Office equipment'), 'B has its own Buses category and not A\'s Office equipment');
    $call('POST', $P . 'fixed-assets/post-depreciation', ['month' => $month, 'reviewed' => 1], $mgrB);
    $ok($in(fn() => DepreciationSchedule::where('fixed_asset_id', $laptop->id)->count()) === 1, 'B\'s month run does not touch A\'s assets');

    // ---- 11. (Report only) a head office's books and a company bus.
    $session->forget('ledger.owner');
    $director = accTestUser('Assets Director'); $mgrC = accTestUser('Assets Manager C');
    $slug = 'fa-co-' . Str::lower(Str::random(6));
    BusCompany::forceCreate(['name' => 'Assets Coaches Co', 'slug' => $slug, 'status' => 'active', 'money_mode' => 'company', 'bill_mode' => 'company',
        'webhook_code' => Str::lower(Str::random(16)), 'turnaround_minutes' => 60]);
    $company = BusCompany::where('slug', $slug)->first();
    BusCompanyStaff::create(['company_id' => $company->id, 'user_id' => $director->id, 'role' => 'director', 'agency_ids' => [], 'active' => true]);
    [$C, $busC] = $mk('Assets Branch Limbe', $mgrC, ['company_id' => $company->id, 'branch_label' => 'Limbe']);
    $call('GET', '/partner/bus-company/accounting', [], $director);
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $director);
    $hoBid = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_company')->where('owner_id', $company->id)->value('id');
    $html = $call('GET', $P . 'fixed-assets/create', [], $director)->getContent();
    $ok(str_contains($html, $busC->registration), 'head office: the company bus is offered in Bus of the fleet');
    $call('POST', $P . 'fixed-assets', ['name' => 'Coach ' . $busC->registration, 'category_id' => $in(fn() => FixedAssetCategory::where('code', 'BUS')->value('id'), $hoBid),
        'vehicle_id' => (string)$busC->id, 'acquisition_date' => $today, 'acquisition_cost' => 24000000, 'useful_life_years' => 10, 'depreciation_method' => 'straight_line'], $director);
    $hoFa = $in(fn() => FixedAsset::where('vehicle_id', (string)$busC->id)->first(), $hoBid);
    $call('POST', $P . 'fixed-assets/post-depreciation', ['month' => $month, 'reviewed' => 1], $director);
    $ok($hoFa && $eq($bal('681', $hoBid), 200000), 'head office: the company bus is an asset there and its month (200 000) is charged in the head office books');
    $session->forget('ledger.owner');
    $call('GET', '/partner/bus-agency/accounting', [], $mgrC);
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrC);
    $cBid = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $C->id)->value('id');
    $ok($eq($bal('681', $cBid), 0), 'REPORT: the home branch gets no charge for the bus\'s wear (stays in the head office books)');
    $html = $call('GET', $P . 'fixed-assets/create', [], $mgrC)->getContent();
    echo '   note: the branch\'s Bus of the fleet ' . (str_contains($html, $busC->registration) ? 'also offers' : 'does not offer') . " the company bus\n";
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
