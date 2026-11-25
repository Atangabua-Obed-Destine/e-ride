<?php
/*
 * paxhitestbed's payroll and tax on a bus branch's books, end to end through the real routes (HTTP
 * kernel, middleware, controllers, review), all rolled back: the Cameroon tax defaults and their
 * figures against paxhitestbed's own test points (cameroon_tax_test.php: the DGI barème checkpoints,
 * 21 005 withheld and 12 060 employer charges on 180 000), the lists, two staff, a month generated
 * (one by hand, one in bulk), paying refused without the review then posted (Dr 661 / 664, Cr 431 /
 * 447 / the payment account), a loan given (Dr 421) and taken back on the next payroll (Cr 421), a
 * tax remittance to the CNPS with its receipt (voided and recorded again), an unpay that reverses,
 * the payslip page and PDF, the staff tax report, every account's balance against its posted lines,
 * the accountant kept from the manager's rights, and a second branch walled off.
 *
 * Run: php tests/Ledger/payroll_test.php
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use App\Ledger\Ledger;
use App\Ledger\Models\{ChartOfAccount, JournalEntry, JournalEntryLine, PaymentAccount, Payroll, Staff, StaffLoan, TaxGroup, TaxRemittance, TaxSetting, TransactionMapping};
use App\Ledger\Payroll\{CameroonTaxTable, PayrollCalculator};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAgency, BusStaff};
use Modules\UserManagement\Entities\User;

$pass = 0; $fail = 0; $fails = [];
$ok = function ($c, $label) use (&$pass, &$fail, &$fails) { if ($c) { $pass++; } else { $fail++; $fails[] = $label; } print(($c ? 'PASS ' : 'FAIL ') . $label . "\n"); };
$eq = fn($a, $b) => abs((float)$a - (float)$b) < 0.01;

DB::beginTransaction();
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    \App\Ledger\LedgerObserver::forget();
    \App\Ledger\Models\PayrollSetting::flush();
    $mgrA = accTestUser('Payroll Manager A'); $accA = accTestUser('Payroll Accountant A'); $mgrB = accTestUser('Payroll Manager B');
    $mk = function (string $name, User $mgr) {
        BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => $name, 'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)), 'origin_city' => 'Bamenda',
            'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => []]);
        $a = BusAgency::where('name', $name)->first();
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
        return $a;
    };
    $A = $mk('Payroll Branch Bamenda', $mgrA);
    $B = $mk('Payroll Branch Kumba', $mgrB);
    BusStaff::create(['agency_id' => $A->id, 'user_id' => $accA->id, 'role' => 'accountant', 'active' => true]);

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
    $flash = fn() => (string)($session->get('status')['msg'] ?? '');
    $P = '/partner/accounting/';

    // ---- 1. Books A, a payment account with money in it.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrA);
    $bidA = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $A->id)->value('id');
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrA);
    $ok($bidA > 0 && Ledger::isSetUp($bidA), 'branch A books opened and set up');
    $in = fn(callable $fn) => Ledger::forBusiness($bidA, $fn);
    $acc = fn(string $code) => (int)$in(fn() => ChartOfAccount::where('account_code', $code)->value('id'));
    $bal = fn(string $code) => round((float)$in(fn() => JournalEntryLine::where('account_id', ChartOfAccount::where('account_code', $code)->value('id'))
        ->whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->selectRaw('COALESCE(SUM(debit - credit), 0) as b')->value('b')), 2);
    $call('GET', $P . 'payment-account', [], $mgrA);
    $cashType = $in(fn() => \App\Ledger\Models\PaymentAccountType::where('kind', 'cash')->value('id'));
    $call('POST', $P . 'payment-account/store', ['title' => 'Main till', 'account_type_id' => $cashType, 'chart_of_account_id' => $acc('571'),
        'opening_balance' => 2000000, 'contra_account_id' => $acc('4711'), 'reviewed' => 1], $mgrA);
    $tillId = $in(fn() => PaymentAccount::where('title', 'Main till')->value('id'));
    $ok($tillId && $eq($bal('571'), 2000000), 'payment account "Main till" with 2 000 000 (Dr 571)');

    // ---- 2. The Cameroon defaults, installed the first time a tax screen opens.
    $r = $call('GET', $P . 'staff/tax-setting', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'tax settings page opens (' . $r->getStatusCode() . ')');
    [$pitBands, $crtvBands, $ldtBands, $cnpsRows, $cac, $ia] = $in(fn() => [
        TaxSetting::where('tax_group_id', TaxGroup::where('code', 'PIT')->value('id'))->count(),
        TaxSetting::where('tax_group_id', TaxGroup::where('code', 'CRTV')->value('id'))->count(),
        TaxSetting::where('tax_group_id', TaxGroup::where('code', 'LDT')->value('id'))->count(),
        TaxSetting::whereNull('tax_group_id')->where('title', 'like', 'National Social Insurance%')->count(),
        TaxSetting::where('is_dependent', 1)->where('depends_on_type', 'tax_group')->exists(),
        TaxGroup::where('code', 'IA')->where('status', 0)->exists(),
    ]);
    $ok($pitBands === 5 && $crtvBands === 11 && $ldtBands === 10, "defaults: IRPP 5 bands, CRTV 11, TDL 10 ($pitBands/$crtvBands/$ldtBands)");
    $ok($cnpsRows === 2 && $cac && $ia, 'defaults: CNPS with its ceiling as two rows, the council tax on the IRPP, industrial accident switched off');
    $ok($in(fn() => TaxSetting::where('status', 1)->whereNull('liability_account_id')->count()) === 0, 'every tax names the account it is owed on');
    $wrong = [];
    $in(function () use (&$wrong) {
        $pit = TaxGroup::where('code', 'PIT')->first();
        foreach (CameroonTaxTable::checkpoints() as $salary => $expected) {
            $pit->unsetRelation('brackets');
            if (abs((int)round($pit->calculateTax($salary)['amount']) - $expected) > 1) {
                $wrong[] = $salary;
            }
        }
    });
    $ok($wrong === [], 'the income tax reproduces the DGI barème at all 16 of paxhitestbed\'s checkpoints' . ($wrong ? ' (' . implode(', ', $wrong) . ')' : ''));
    $t180 = $in(fn() => app(PayrollCalculator::class)->taxFor(0, 180000, today()->startOfMonth()));
    $ok($eq($t180['employee'], 21005) && $eq($t180['employer'], 12060), '180 000: 21 005 withheld, 12 060 employer charges (paxhitestbed\'s figures) (' . $t180['employee'] . ' / ' . $t180['employer'] . ')');
    $line = fn($t, $label) => collect($t['lines'])->first(fn($l) => str_contains($l['label'], $label));
    $ok($eq($line($t180, 'Council')['employee'], 768) && $eq($line($t180, 'Personal Income')['employee'], 7677), '180 000: IRPP 7 677, CAC a tenth (768)');
    $ok($eq($line($t180, 'Housing')['employee'], 1800) && $eq($line($t180, 'Housing')['employer'], 2700), '180 000: CFC 1% staff, 1.5% employer');
    $cap = $in(fn() => app(PayrollCalculator::class)->taxFor(0, 2000000, today()->startOfMonth()));
    $ok($eq($line($cap, 'Social Insurance')['employee'], 75000), 'CNPS capped at 75 000 above 1 785 715');

    // ---- 3. The lists.
    foreach (['designation' => 'Driver', 'department' => 'Operations'] as $p => $title) {
        $call('POST', $P . "staff/$p", ['title' => $title], $mgrA);
    }
    $call('POST', $P . 'staff/allowance-type', ['title' => 'Transport', 'is_taxable' => 0, 'status' => 1], $mgrA);
    $call('POST', $P . 'staff/allowance-type', ['title' => 'Housing', 'is_taxable' => 1, 'status' => 1], $mgrA);
    $call('POST', $P . 'staff/deduction-type', ['title' => 'Penalty', 'status' => 1], $mgrA);
    [$desId, $depId] = $in(fn() => [\App\Ledger\Models\Designation::where('title', 'Driver')->value('id'), \App\Ledger\Models\Department::where('title', 'Operations')->value('id')]);
    $ok($desId && $depId && $in(fn() => \App\Ledger\Models\AllowanceType::count()) === 2 && $in(fn() => \App\Ledger\Models\DeductionType::count()) === 1, 'designation, department, two allowance types and a deduction type made');
    foreach (['staff/designation', 'staff/department', 'staff/allowance-type', 'staff/deduction-type', 'staff/tax-group', 'staff/pay-slip-setting', 'staff/payroll-setting'] as $p) {
        $r = $call('GET', $P . $p, [], $mgrA);
        $ok($r->getStatusCode() === 200, "page $p opens (" . $r->getStatusCode() . ')');
    }

    // ---- 4. Two staff on the register (one linked to an E-Ride user of the branch).
    $call('POST', $P . 'staff/register', ['staff_no' => 'DRV01', 'first_name' => 'Alice', 'last_name' => 'Nkeng', 'designation_id' => $desId, 'department_id' => $depId,
        'basic_salary' => 180000, 'pay_channel' => 'mobile_money', 'momo_provider' => 'mtn', 'momo_number' => '677000001', 'cnps_no' => 'C-1', 'niu' => 'P1'], $mgrA);
    $call('POST', $P . 'staff/register', ['staff_no' => 'CON01', 'first_name' => 'Bob', 'last_name' => 'Fon', 'department_id' => $depId,
        'basic_salary' => 500000, 'pay_channel' => 'bank', 'bank_name' => 'Afriland', 'account_number' => '00123', 'user_id' => $accA->id], $mgrA);
    [$alice, $bob] = $in(fn() => [Staff::where('staff_no', 'DRV01')->first(), Staff::where('staff_no', 'CON01')->first()]);
    $ok($alice && $bob && $bob->user_id === (string)$accA->id, 'two staff registered, Bob linked to his E-Ride account');
    $r = $call('GET', $P . 'staff/register', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Alice'), 'staff list opens and lists them');

    // ---- 5. Generate this month: Alice by hand (with a Transport allowance), Bob in bulk.
    $M = today()->startOfMonth();
    $call('GET', $P . "staff/payroll-generate/{$alice->id}/{$M->month}/{$M->year}", [], $mgrA);
    $prev = $call('POST', $P . 'staff/payroll-preview', ['staff_id' => $alice->id, 'salary_month' => $M->toDateString(),
        'allowance_titles' => ['Transport'], 'allowances' => [20000]], $mgrA, true);
    $pv = json_decode($prev->getContent(), true);
    $ok($prev->getStatusCode() === 200 && $eq($pv['tax'] ?? 0, 21005) && $eq($pv['net_salary'] ?? 0, 178995) && empty($pv['problems']), 'preview: an untaxed allowance leaves the tax at 21 005, net 178 995');
    $r = $call('GET', $P . "staff/payroll-generate/{$alice->id}/{$M->month}/{$M->year}", [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'taxBreakdownPanel'), 'generate screen opens with its tax panel');
    $call('POST', $P . 'staff/payroll', ['staff_id' => $alice->id, 'salary_month' => $M->toDateString(), 'allowance_titles' => ['Transport'], 'allowances' => [20000], 'net_salary' => 190000], $mgrA);
    $ok(!$in(fn() => Payroll::where('staff_id', $alice->id)->exists()), 'a payroll whose posted net disagrees with the server is refused');
    $call('POST', $P . 'staff/payroll', ['staff_id' => $alice->id, 'salary_month' => $M->toDateString(), 'allowance_titles' => ['Snacks'], 'allowances' => [5000]], $mgrA);
    $ok(!$in(fn() => Payroll::where('staff_id', $alice->id)->exists()), 'an allowance that is not an allowance type is refused');
    $call('POST', $P . 'staff/payroll', ['staff_id' => $alice->id, 'salary_month' => $M->toDateString(), 'allowance_titles' => ['Transport'], 'allowances' => [20000], 'net_salary' => 178995], $mgrA);
    $pa = $in(fn() => Payroll::where('staff_id', $alice->id)->first());
    $ok($pa && $eq($pa->tax, 21005) && $eq($pa->employer_tax, 12060) && $eq($pa->gross_salary, 200000) && $eq($pa->net_salary, 178995) && $pa->status === 0, 'Alice generated: gross 200 000, tax 21 005, employer 12 060, net 178 995');
    $ok($in(fn() => \App\Ledger\Models\PayrollTaxLine::where('payroll_id', $pa->id)->sum('employee_amount')) == 21005, 'its itemised taxes add up to the tax charged');
    $ok(!$in(fn() => TransactionMapping::where('transaction_type', 'payroll')->where('status', 'posted')->exists()), 'generating posts nothing');
    $call('POST', $P . 'staff/payroll-bulk', ['action' => 'generate', 'month' => $M->month, 'year' => $M->year, 'staff' => [$bob->id, $alice->id]], $mgrA);
    $pb = $in(fn() => Payroll::where('staff_id', $bob->id)->whereDate('salary_month', $M)->first());
    // Bob, 500 000: IRPP 38 500, CAC 3 850, CNPS 21 000, CFC 5 000, CRTV 5 850, TDL 2 250 / employer CNPS 21 000, CFC 7 500, FNE 5 000
    $ok($pb && $eq($pb->tax, 76450) && $eq($pb->employer_tax, 33500), 'Bob generated in bulk: 76 450 withheld, 33 500 employer (' . ($pb->tax ?? '-') . ' / ' . ($pb->employer_tax ?? '-') . ')');
    $ok(str_contains(json_encode(session('payroll_bulk_result') ?? $session->get('payroll_bulk_result')), 'already generated'), 'bulk skips Alice, already generated, and says so');
    $r = $call('GET', $P . "staff/payroll?month={$M->month}&year={$M->year}", [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'DRV01') && str_contains($r->getContent(), 'payModal-'), 'payroll list opens with the pay buttons');

    // ---- 6. The accountant cannot do the manager's things.
    $call('GET', '/partner/bus-agency/accounting', [], $accA);
    $r = $call('POST', $P . "staff/payroll-approve/{$pa->id}", [], $accA);
    $ok($r->getStatusCode() === 403 && !$in(fn() => Payroll::find($pa->id)->approved_at), 'the accountant cannot approve a payroll (403)');
    $r = $call('POST', $P . 'staff/payroll-setting', ['require_approval' => 1], $accA);
    $ok($r->getStatusCode() === 403, 'the accountant cannot change the payroll settings (403)');
    $r = $call('GET', $P . 'staff/payroll', [], $accA);
    $ok($r->getStatusCode() === 200, 'the accountant can open the payroll');
    $call('GET', '/partner/bus-agency/accounting', [], $mgrA);
    $call('POST', $P . 'staff/payroll-setting', ['require_approval' => 1, 'carry_over_lines' => 1, 'prorate_partial_months' => 0, 'proration_basis' => 'calendar_days',
        'bank_file_columns' => ['staff_id', 'name', 'mobile_money', 'account_number', 'amount'], 'bank_file_delimiter' => ';', 'bank_file_header' => 1,
        'bank_file_reference' => 'SALARY {MONTH} {YEAR}', 'bank_file_decimals' => 0, 'advance_payment_account_id' => $tillId], $mgrA);
    \App\Ledger\Models\PayrollSetting::flush();
    $ok($in(fn() => \App\Ledger\Models\PayrollSetting::current()->require_approval), 'the manager turns approval on');

    // ---- 7. Pay Alice: refused unapproved, refused without the review, then posted.
    $pay = ['pay_date' => today()->toDateString(), 'payment_method' => 'cash', 'payment_account_id' => $tillId, 'note' => 'November pay'];
    $call('POST', $P . "staff/payroll-pay/{$pa->id}", $pay + ['reviewed' => 1], $mgrA);
    $ok($in(fn() => Payroll::find($pa->id)->status) === 0 && str_contains($flash(), 'approved'), 'an unapproved payroll is not paid when approval is required');
    $call('POST', $P . "staff/payroll-approve/{$pa->id}", [], $mgrA);
    $call('POST', $P . "staff/payroll-approve/{$pb->id}", [], $mgrA);
    $ok((bool)$in(fn() => Payroll::find($pa->id)->approved_at), 'the manager approves');
    $call('POST', $P . "staff/payroll-pay/{$pa->id}", $pay, $mgrA);
    $ok($in(fn() => Payroll::find($pa->id)->status) === 0, 'paying without the review tick is refused');
    $r = $call('POST', $P . 'review/dry-run', $pay + ['_token' => 'tok', '_review_target' => $P . "staff/payroll-pay/{$pa->id}", '_review_method' => 'POST'], $mgrA, true);
    $html = $r->getContent();
    $ok($r->getStatusCode() === 200 && str_contains($html, 'ap-preview') && str_contains($html, '661') && str_contains($html, '431') && str_contains($html, '447') && str_contains($html, '571'),
        'review dialog shows the salary entry (661, 431, 447, 571)');
    $ok($in(fn() => Payroll::find($pa->id)->status) === 0 && $eq($bal('661'), 0), 'the review paid and posted nothing');
    $call('POST', $P . "staff/payroll-pay/{$pa->id}", $pay + ['reviewed' => 1], $mgrA);
    $ok($in(fn() => Payroll::find($pa->id)->status) === 1, 'Alice paid through the review');
    // 447: IRPP 7 677 + CAC 768 + CFC 1 800 + 2 700 + CRTV 1 950 + TDL 1 250 = 16 145; 431: CNPS 7 560 x 2 + FNE 1 800 = 16 920
    $ok($eq($bal('661'), 200000) && $eq($bal('664'), 12060) && $eq($bal('447'), -16145) && $eq($bal('431'), -16920) && $eq($bal('571'), 2000000 - 178995),
        'posted: Dr 661 200 000, Dr 664 12 060 / Cr 447 16 145, Cr 431 16 920, Cr 571 178 995');
    $ok($in(fn() => PaymentAccount::find($tillId)->current_balance) == 2000000 - 178995, 'and the till\'s book moved by the net pay');

    // ---- 8. A loan to Bob, repaid from next month's payroll.
    $N = $M->copy()->addMonthNoOverflow();
    $loanIn = ['staff_id' => $bob->id, 'kind' => 'advance', 'title' => 'Salary advance', 'amount' => 100000, 'instalment' => 40000, 'start_month' => $N->format('Y-m'),
        'given_on' => today()->toDateString(), 'payment_account_id' => $tillId];
    $call('POST', $P . 'staff/staff-loan', $loanIn, $mgrA);
    $ok(!$in(fn() => StaffLoan::exists()), 'a loan without the review tick is refused');
    $call('POST', $P . 'staff/staff-loan', $loanIn + ['reviewed' => 1], $mgrA);
    $loan = $in(fn() => StaffLoan::first());
    $ok($loan && $eq($bal('421'), 100000) && $eq($bal('571'), 2000000 - 178995 - 100000), 'loan given: Dr 421 100 000 / Cr 571');
    $r = $call('GET', $P . 'staff/staff-loan', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Salary advance'), 'loans page lists it');
    $call('POST', $P . "staff/payroll-pay/{$pb->id}", $pay + ['reviewed' => 1], $mgrA);
    $ok($in(fn() => Payroll::find($pb->id)->status) === 1, 'Bob\'s month paid (no repayment due before the start month)');
    $call('POST', $P . 'staff/payroll-bulk', ['action' => 'generate', 'month' => $N->month, 'year' => $N->year, 'staff' => [$bob->id]], $mgrA);
    $pn = $in(fn() => Payroll::with('details')->where('staff_id', $bob->id)->whereDate('salary_month', $N)->first());
    $rep = $pn ? $pn->details->firstWhere('staff_loan_id', $loan->id) : null;
    $ok($pn && $rep && $eq($rep->amount, 40000) && $eq($pn->gross_salary, 460000) && $eq($pn->tax, 76450), 'next month starts with the 40 000 repayment (gross 460 000, tax still on 500 000)');
    $call('POST', $P . "staff/payroll-approve/{$pn->id}", [], $mgrA);
    $before421 = $bal('421'); $before661 = $bal('661');
    $call('POST', $P . "staff/payroll-pay/{$pn->id}", $pay + ['reviewed' => 1], $mgrA);
    $ok($in(fn() => Payroll::find($pn->id)->status) === 1 && $eq($bal('421'), $before421 - 40000) && $eq($bal('661') - $before661, 500000),
        'paid: Cr 421 40 000 taken back, Dr 661 the full 500 000');
    $ok($in(fn() => StaffLoan::find($loan->id)->repaid()) == 40000 && $in(fn() => StaffLoan::find($loan->id)->balance()) == 60000, 'the loan shows 40 000 repaid, 60 000 still owed');
    $call('POST', $P . "staff/staff-loan/{$loan->id}/cancel", ['reviewed' => 1], $mgrA);
    $ok($in(fn() => StaffLoan::find($loan->id)->status) === 'active', 'a loan already on a payroll cannot be cancelled');

    // ---- 9. Unpay reverses.
    $entries = $in(fn() => JournalEntry::count());
    $call('POST', $P . "staff/payroll-unpay/{$pn->id}", [], $mgrA);
    $ok($in(fn() => Payroll::find($pn->id)->status) === 1, 'unpay without the review tick is refused');
    $call('POST', $P . "staff/payroll-unpay/{$pn->id}", ['reviewed' => 1], $mgrA);
    $map = $in(fn() => TransactionMapping::where('transaction_type', 'payroll')->where('transaction_id', (string)$pn->id)->first());
    $ok($in(fn() => Payroll::find($pn->id)->status) === 0 && $eq($bal('421'), $before421) && $eq($bal('661'), $before661) && $in(fn() => JournalEntry::count()) === $entries + 1,
        'unpaid: one reversing entry, 421 and 661 back where they were');
    $ok($map && $map->status === 'none' && $in(fn() => JournalEntry::where('reference_type', 'payroll')->where('is_reversed', true)->count()) === 1, 'the original entry is marked reversed, not deleted');
    $ok($in(fn() => \App\Ledger\Models\PayrollTaxLine::where('payroll_id', $pn->id)->count()) === 0 && $in(fn() => StaffLoan::find($loan->id)->repaid()) == 0, 'its withholdings and the repayment no longer count');

    // ---- 10. Tax remittance to the CNPS for this month, with the receipt number.
    $cnps = $acc('431');
    $owed = collect($in(fn() => app(\App\Ledger\Payroll\TaxRemittanceService::class)->outstanding()))->first(fn($r) => $r['account_id'] === $cnps && $r['month'] === $M->toDateString());
    // Alice 16 920 + Bob CNPS 21 000 x 2 + FNE 5 000 = 63 920
    $ok($owed && $eq($owed['due'], 63920) && $owed['status'] === 'undeclared', 'owed to the CNPS for the month: 63 920, not declared (' . ($owed['due'] ?? '-') . ')');
    $r = $call('GET', $P . 'staff/tax-remittance', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), '63 920'), 'tax remittance page opens and shows it');
    $remit = ['liability_account_id' => $cnps, 'salary_month' => $M->toDateString(), 'amount' => 63920, 'payment_date' => today()->toDateString(),
        'payment_account_id' => $tillId, 'reference' => 'CNPS-4471'];
    $call('POST', $P . 'staff/tax-remittance', $remit, $mgrA);
    $ok(!$in(fn() => TaxRemittance::exists()), 'a remittance without the review tick is refused');
    $call('POST', $P . 'staff/tax-remittance', ['amount' => 70000] + $remit + ['reviewed' => 1], $mgrA);
    $ok(!$in(fn() => TaxRemittance::exists()), 'paying more than is owed is refused without the overpayment tick');
    $call('POST', $P . 'staff/tax-remittance', $remit + ['reviewed' => 1], $mgrA);
    $rm = $in(fn() => TaxRemittance::first());
    $ok($rm && $rm->reference === 'CNPS-4471', 'remittance recorded with its receipt number');
    $ok($eq($bal('431'), 0), 'Dr 431 63 920: nothing left owed to the CNPS');
    $call('POST', $P . "staff/tax-remittance/{$rm->id}/void", ['void_reason' => 'Wrong receipt', 'reviewed' => 1], $mgrA);
    $ok($in(fn() => TaxRemittance::find($rm->id)->isVoided()) && $eq($bal('431'), -63920), 'voided: reversed, the CNPS is owed again, the row kept');
    $call('POST', $P . 'staff/tax-remittance', ['reference' => 'CNPS-4472'] + $remit + ['reviewed' => 1], $mgrA);
    $ok($eq($bal('431'), 0) && $in(fn() => TaxRemittance::whereNull('voided_at')->count()) === 1, 'recorded again: settled');
    $owed = collect($in(fn() => app(\App\Ledger\Payroll\TaxRemittanceService::class)->outstanding()))->first(fn($r) => $r['account_id'] === $cnps && $r['month'] === $M->toDateString());
    $ok($owed && $owed['status'] === 'settled', 'the month shows settled');

    // ---- 11. Payslip, PDF, report, staff tax report, bank file.
    $r = $call('GET', $P . "staff/payroll-print/{$pa->id}", [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Alice') && str_contains($r->getContent(), '21 005'), 'payslip page opens with the tax withheld');
    $r = $call('GET', $P . "staff/payroll-pdf/{$pa->id}", [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_starts_with((string)$r->getContent(), '%PDF'), 'payslip PDF opens');
    $r = $call('GET', $P . "staff/payroll-report?month={$M->month}&year={$M->year}", [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), '178 995'), 'payroll report opens');
    $r = $call('GET', $P . 'staff/tax-report', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), '21 005') && str_contains($r->getContent(), '76 450'), 'staff tax report opens, recomputed with the payroll engine');
    $r = $call('GET', $P . 'staff/tax-report/pdf', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'staff tax report PDF');
    $r = $call('GET', $P . "staff/payroll-bank-file?month={$M->month}&year={$M->year}&which=paid", [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains((string)$r->getContent(), '677000001') && str_contains((string)$r->getContent(), ';'), 'bank file in the chosen layout');
    $r = $call('GET', $P . 'mappings/transactions?type=payroll', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'View transactions lists payrolls');
    $html = $call('GET', $P . 'mappings/settings', [], $mgrA)->getContent();
    $ok(str_contains($html, __('ledger_payroll.map_staff_advances')), 'Mapping settings has the payroll group');

    $pitId = $in(fn() => TaxGroup::where('code', 'PIT')->value('id'));
    foreach (["staff/tax-group/$pitId", "staff/tax-group/$pitId/edit", 'staff/tax-group/create', 'staff/register/create', "staff/register/{$bob->id}/edit"] as $p) {
        $r = $call('GET', $P . $p, [], $mgrA);
        $ok($r->getStatusCode() === 200, "page $p opens (" . $r->getStatusCode() . ')');
    }
    $ok(__('ledger_payroll.pay_ticked', [], 'fr') === 'Payer les cochés' && count(require base_path('resources/lang/fr/ledger_payroll.php')) === count(require base_path('resources/lang/en/ledger_payroll.php')),
        'every payroll string has its French');

    // ---- 12. Everything balances.
    $in(function () use ($ok) {
        $bad = JournalEntry::where('is_posted', true)->get()->filter(fn($e) => abs($e->lines()->sum('debit') - $e->lines()->sum('credit')) > 0.005)->count();
        $ok($bad === 0, 'every posted entry balances');
        $wrongAcc = [];
        foreach (ChartOfAccount::where('account_category', 'detail')->get() as $a) {
            $s = JournalEntryLine::where('account_id', $a->id)->whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->selectRaw('COALESCE(SUM(debit - credit),0) b')->value('b');
            $expect = ($a->normal_balance === 'credit' ? -1 : 1) * (float)$s;
            if (abs(((float)$a->current_balance - (float)$a->opening_balance) - $expect) > 0.01) {
                $wrongAcc[] = $a->account_code;
            }
        }
        $ok($wrongAcc === [], 'every account\'s balance equals its posted lines' . ($wrongAcc ? ' (' . implode(', ', $wrongAcc) . ')' : ''));
        \App\Ledger\Services\LedgerSync::flush();
        $stats = (new \App\Ledger\Services\LedgerSync())->sweep();
        $ok(empty($stats['posted']) && empty($stats['reposted']) && empty($stats['failed']), 'the sweep finds nothing to post again (' . json_encode($stats) . ')');
    });

    // ---- 13. Branch B sees none of it.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrB);
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrB);
    foreach (['staff/payroll', 'staff/register', 'staff/staff-loan', 'staff/tax-remittance', 'staff/tax-report', "staff/payroll-report?month={$M->month}&year={$M->year}"] as $p) {
        $html = $call('GET', $P . $p, [], $mgrB)->getContent();
        $ok(!str_contains($html, 'Alice') && !str_contains($html, 'DRV01') && !str_contains($html, 'CON01'), "branch B's $p shows nothing of branch A");
    }
    foreach (["staff/payroll-print/{$pa->id}", "staff/payroll-generate/{$alice->id}/{$M->month}/{$M->year}"] as $p) {
        $r = $call('GET', $P . $p, [], $mgrB);
        $ok($r->getStatusCode() === 404, "branch B cannot open A's $p (" . $r->getStatusCode() . ')');
    }
    $call('POST', $P . "staff/payroll-unpay/{$pa->id}", ['reviewed' => 1], $mgrB);
    $ok($in(fn() => Payroll::find($pa->id)->status) === 1, "branch B cannot unpay A's payroll");
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
