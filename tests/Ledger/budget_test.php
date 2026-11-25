<?php
/*
 * posinnova's budgets on a bus branch's books, end to end through the real routes, all rolled back:
 * the budget lines (standard set from the chart, one narrowed to a department), the Income &
 * Expenditure sheet with its figures (submitted, approved through the review, activated, PDF and
 * Excel), a departmental budget for a payroll department with allocations by expense category and
 * by account (approved through the review, activated, revised), expenses for that department, for
 * another one and for none, an income, a payroll paid for a staff member of the department; then the
 * actuals, the variances and Budget vs Actual against the posted ledger, every budget page and report,
 * and a second branch that sees nothing of the first's.
 *
 * Run: php tests/Ledger/budget_test.php
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use App\Ledger\Ledger;
use App\Ledger\Models\{Budget, BudgetAllocation, BudgetLine, ChartOfAccount, FiscalYear, PaymentAccount};
use App\Ledger\Services\BudgetActualsService;
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
    $mgrA = accTestUser('Budget Manager A'); $accA = accTestUser('Budget Accountant A'); $mgrB = accTestUser('Budget Manager B');
    $mk = function (string $name, User $mgr) {
        BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => $name, 'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)), 'origin_city' => 'Bamenda',
            'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => []]);
        $a = BusAgency::where('name', $name)->first();
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
        return $a;
    };
    $A = $mk('Budget Branch Bamenda', $mgrA);
    BusStaff::create(['agency_id' => $A->id, 'user_id' => $accA->id, 'role' => 'accountant', 'active' => true]);
    $B = $mk('Budget Branch Kumba', $mgrB);

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
    $today = today()->toDateString();

    // ---- 1. Books A, categories, a payment account, two departments.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrA);
    $bidA = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $A->id)->value('id');
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrA);
    $ok($bidA > 0 && Ledger::isSetUp($bidA), 'branch A books opened and set up');
    $in = fn(callable $fn) => Ledger::forBusiness($bidA, $fn);
    $acc = fn(string $code) => (int)$in(fn() => ChartOfAccount::where('account_code', $code)->value('id'));
    $tb = fn(string $prefix) => round((float)$in(fn() => DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
        ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')->where('l.business_id', $bidA)->where('j.is_posted', 1)->whereNull('j.deleted_at')
        ->where('a.account_code', 'like', $prefix . '%')->sum(DB::raw('l.debit - l.credit'))), 2);
    $year = $in(fn() => FiscalYear::forDate($today));
    [$ys, $ye] = [$year->start_date->toDateString(), $year->end_date->toDateString()];

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
    $cashType = $in(fn() => \App\Ledger\Models\PaymentAccountType::where('kind', 'cash')->value('id'));
    $call('POST', $P . 'payment-account/store', ['title' => 'Main till', 'account_type_id' => $cashType, 'chart_of_account_id' => $acc('571'),
        'opening_balance' => 2000000, 'contra_account_id' => $acc('4711'), 'reviewed' => 1], $mgrA);
    $tillId = $in(fn() => PaymentAccount::where('title', 'Main till')->value('id'));
    foreach (['Operations', 'Administration'] as $t) {
        $call('POST', $P . 'staff/department', ['title' => $t], $mgrA);
    }
    [$ops, $adm] = $in(fn() => [\App\Ledger\Models\Department::where('title', 'Operations')->value('id'), \App\Ledger\Models\Department::where('title', 'Administration')->value('id')]);
    $ok($catFuel && $catOffice && $tillId && $ops && $adm, 'categories, a payment account and two payroll departments made through the routes');

    // ---- 2. Budget lines: the standard set from the chart, and one narrowed to a department.
    $r = $call('GET', $P . 'budget-line', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Operations'), 'budget lines open and offer the departments (' . $r->getStatusCode() . ')');
    $call('POST', $P . 'budget-line/standard', [], $mgrA);
    $line = fn(string $code) => $in(fn() => BudgetLine::where('code', $code)->first());
    $ok($line('605') && $line('707') && $line('66') && $line('66')->is_header && $line('605')->account_id === $acc('605'), 'standard lines from the chart (605, 707 under 70, heading 66)');
    $call('POST', $P . 'budget-line/store', ['code' => '605-OPS', 'name' => 'Fuel for Operations', 'section' => 'expenditure', 'account_id' => $acc('605'),
        'expense_category_id' => $catFuel, 'location_id' => $ops, 'parent_id' => $line('60')->id ?? null], $mgrA);
    $ok((bool)$line('605-OPS') && (int)$line('605-OPS')->location_id === $ops && $line('605-OPS')->location->name === 'Operations', 'a line narrowed to Fuel for the Operations department');
    $call('POST', $P . 'budget-line/store', ['code' => '605-X', 'name' => 'Elsewhere', 'section' => 'expenditure', 'account_id' => $acc('605'), 'location_id' => 999999], $mgrA);
    $ok(!$line('605-X'), "a department of another set of books is refused");

    // ---- 3. The Income & Expenditure sheet for the year: figures, submit, approve (review), activate.
    $call('POST', $P . 'budget-sheet/store', ['title' => 'I&E ' . $year->name, 'start_date' => $ys, 'end_date' => $ye, 'opening_balance' => 0], $mgrA);
    $sheet = $in(fn() => Budget::where('is_institutional', true)->first());
    $ok($sheet && $sheet->status === 'draft', 'the sheet is made (draft)');
    $call('POST', $P . "budget-sheet/{$sheet->id}/figures", ['amounts' => [$line('605')->id => 200000, $line('605-OPS')->id => 300000, $line('707')->id => 100000, $line('661')->id => 2400000]], $mgrA);
    $call('POST', $P . "budget-sheet/{$sheet->id}/submit", [], $mgrA);
    $call('POST', $P . "budget-sheet/{$sheet->id}/approve", [], $mgrA);
    $ok($in(fn() => Budget::find($sheet->id)->status) === 'pending_approval', 'approving the sheet without the review is refused');
    $call('POST', $P . "budget-sheet/{$sheet->id}/approve", ['reviewed' => 1], $accA);
    $ok($in(fn() => Budget::find($sheet->id)->status) === 'pending_approval', 'the accountant cannot approve the sheet (manager only)');
    $call('POST', $P . "budget-sheet/{$sheet->id}/approve", ['reviewed' => 1], $mgrA);
    $call('POST', $P . "budget-sheet/{$sheet->id}/activate", [], $mgrA);
    $sheet = $in(fn() => Budget::find($sheet->id));
    $ok($sheet->status === 'active' && $sheet->approved_at, 'sheet approved through the review, then activated (' . $sheet->status . ')');

    // ---- 4. A departmental budget for Operations: allocations by category and by account.
    $call('POST', $P . 'budget/store', ['title' => 'Operations ' . $year->name, 'type' => 'departmental', 'fiscal_year' => $year->name, 'start_date' => $ys, 'end_date' => $ye,
        'total_amount' => 600000, 'location_id' => $ops, 'parent_id' => $sheet->id], $mgrA);
    $dep = $in(fn() => Budget::where('type', 'departmental')->first());
    $ok($dep && (int)$dep->location_id === $ops && $dep->status === 'draft', 'departmental budget for Operations made (draft)');
    $call('POST', $P . "budget/{$dep->id}/allocations/store", ['title' => 'Fuel', 'expense_category_id' => $catFuel, 'allocated_amount' => 100000, 'period' => 'yearly'], $mgrA);
    $call('POST', $P . "budget/{$dep->id}/allocations/store", ['title' => 'Office', 'expense_category_id' => $catOffice, 'allocated_amount' => 5000, 'period' => 'yearly'], $mgrA);
    $call('POST', $P . "budget/{$dep->id}/allocations/store", ['title' => 'Staff', 'account_id' => $acc('66'), 'allocated_amount' => 400000, 'period' => 'yearly'], $mgrA);
    $call('POST', $P . "budget/{$dep->id}/allocations/store", ['title' => 'Too much', 'account_id' => $acc('624'), 'allocated_amount' => 200000, 'period' => 'yearly'], $mgrA);
    [$aFuel, $aOffice, $aStaff] = $in(fn() => [BudgetAllocation::where('title', 'Fuel')->first(), BudgetAllocation::where('title', 'Office')->first(), BudgetAllocation::where('title', 'Staff')->first()]);
    $ok($aFuel && $aOffice && $aStaff && !$in(fn() => BudgetAllocation::where('title', 'Too much')->exists()), 'three allocations; one beyond the unallocated budget refused');
    $call('POST', $P . "budget/{$dep->id}/submit", [], $mgrA);
    $call('POST', $P . "budget/{$dep->id}/approve", [], $mgrA);
    $ok($in(fn() => Budget::find($dep->id)->status) === 'pending_approval', 'approving the budget without the review is refused');
    $call('POST', $P . "budget/{$dep->id}/approve", ['reviewed' => 1], $mgrA);
    $call('POST', $P . "budget/{$dep->id}/activate", [], $mgrA);
    $ok($in(fn() => Budget::find($dep->id)->status) === 'active', 'departmental budget approved through the review, then activated');

    // ---- 5. Money: expenses for Operations, for Administration and for no department; an income.
    $exp = function (string $title, int $cat, float $amount, ?int $dept) use ($call, $P, $mgrA, $today, $tillId) {
        $call('POST', $P . 'account/expense', ['category' => $cat, 'title' => $title, 'amount' => $amount, 'date' => $today, 'payment_method' => 'cash',
            'payment_account_id' => $tillId, 'department_id' => $dept, 'reviewed' => 1], $mgrA);
    };
    $r = $call('GET', $P . 'account/expense/create', [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'name="department_id"') && str_contains($r->getContent(), 'Administration'), 'the expense form offers the department');
    $exp('Diesel Ops', $catFuel, 30000, $ops);
    $exp('Diesel Admin car', $catFuel, 12000, $adm);
    $exp('Diesel unassigned', $catFuel, 5000, null);
    $exp('Paper Ops', $catOffice, 8000, $ops);
    $call('POST', $P . 'account/income', ['category' => $catParcel, 'title' => 'Parcel desk', 'amount' => 25000, 'date' => $today, 'payment_method' => 'cash', 'payment_account_id' => $tillId, 'reviewed' => 1], $mgrA);
    $ok($in(fn() => \App\Ledger\Models\Expense::where('title', 'Diesel Ops')->value('department_id')) == $ops && $eq($tb('6053'), 47000) && $eq($tb('6055'), 8000) && $eq($tb('707'), -25000),
        'expenses posted (6053 47 000, 6055 8 000) with their departments; income 25 000 on 707');

    // a payroll for a staff member of Operations (the payroll port), paid through the review
    $call('GET', $P . 'staff/tax-setting', [], $mgrA);
    $call('POST', $P . 'staff/register', ['staff_no' => 'OPS01', 'first_name' => 'Alice', 'last_name' => 'Nkeng', 'department_id' => $ops,
        'basic_salary' => 180000, 'pay_channel' => 'cash'], $mgrA);
    $staff = $in(fn() => \App\Ledger\Models\Staff::where('staff_no', 'OPS01')->first());
    if ($staff) {
        $call('POST', $P . 'staff/payroll', ['staff_id' => $staff->id, 'salary_month' => today()->startOfMonth()->toDateString()], $mgrA);
        $pay = $in(fn() => \App\Ledger\Models\Payroll::where('staff_id', $staff->id)->first());
        if ($pay) {
            $call('POST', $P . "staff/payroll-pay/{$pay->id}", ['pay_date' => $today, 'payment_method' => 'cash', 'payment_account_id' => $tillId, 'reviewed' => 1], $mgrA);
        }
    }
    $staffCost = $tb('66');
    $ok($staff && $staffCost > 0, 'payroll for Operations paid and posted (66: ' . $staffCost . ')' . ($staff ? '' : ' [payroll port: staff not registered: ' . $flash() . ']'));

    // ---- 6. Actuals: departmental allocations, the sheet's lines, Budget vs Actual, all from the ledger.
    $r = $call('GET', $P . "budget/{$dep->id}", [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Operations'), 'departmental budget opens and names its department');
    [$aFuel, $aOffice, $aStaff, $depB] = $in(fn() => [BudgetAllocation::find($aFuel->id), BudgetAllocation::find($aOffice->id), BudgetAllocation::find($aStaff->id), Budget::find($dep->id)]);
    $ok($eq($aFuel->spent_amount, 30000), 'Fuel for Operations: 30 000 spent (not the Administration car, not the unassigned diesel) (' . $aFuel->spent_amount . ')');
    $ok($eq($aOffice->spent_amount, 8000) && $eq($aOffice->remaining_amount, -3000), 'Office for Operations: 8 000 spent of 5 000, 3 000 over');
    $ok($eq($aStaff->spent_amount, $staffCost), 'Staff (66) for Operations: the payroll of its staff member (' . $aStaff->spent_amount . ')');
    $ok($eq($depB->spent_amount, 38000 + $staffCost) && $eq($depB->remaining_amount, 600000 - 38000 - $staffCost), 'the budget: spent and remaining add up');
    $mv = $in(fn() => app(BudgetActualsService::class)->movements($ys, $ye));
    $ok($eq($mv->where('location_id', $ops)->where('class_number', 6)->sum('amount'), 38000 + $staffCost) && $eq($mv->where('location_id', $adm)->sum('amount'), 12000),
        'ledger movements by department: Operations 38 000 + payroll, Administration 12 000');
    $ok($eq($mv->where('class_number', 6)->sum('amount'), $tb('6')) && $eq($mv->where('class_number', 7)->sum('amount'), -$tb('7')), 'the movements are classes 6 and 7 of the trial balance');

    $act = $in(fn() => app(BudgetActualsService::class)->forPeriod($ys, $ye));
    $ok($eq($act['lines'][$line('605-OPS')->id] ?? 0, 30000), 'sheet: the Fuel for Operations line takes the Operations diesel (30 000)');
    $ok($eq($act['lines'][$line('605')->id] ?? 0, 12000 + 5000 + 8000), 'sheet: 605 takes the rest of 605 (25 000)');
    $ok($eq($act['lines'][$line('707')->id] ?? 0, 25000), 'sheet: 707 takes the income (25 000)');
    $ok($eq(array_sum($act['lines']) + array_sum($act['unallocated']), -$tb('7') + $tb('6') + $tb('2') - $tb('28') - $tb('29')), 'every franc of classes 6 and 7 lands on one line or in unallocated');

    $html = $call('GET', $P . 'accounting-reports/budget-vs-actual?period=full', [], $mgrA)->getContent();
    $m = fn($v) => $in(fn() => Ledger::money($v));
    $ok(str_contains($html, 'Fuel for Operations') && str_contains($html, $m(300000)) && str_contains($html, $m(30000)), 'Budget vs Actual shows the line, its budget (300 000) and actual (30 000)');
    $ok(!str_contains($html, e(__('ledger_budget.bva_no_budget', ['year' => $year->name]))), 'Budget vs Actual reads the active sheet');
    $r = $call('GET', $P . 'accounting-reports/budget-vs-actual?export=excel', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'Budget vs Actual Excel downloads');

    // the reports of the departmental budgets
    $html = $call('GET', $P . 'budget-reports/performance', [], $mgrA)->getContent();
    $ok(str_contains($html, 'Fuel') && str_contains($html, $m(30000)) && str_contains($html, $m(100000)), 'Performance: Fuel 30 000 of 100 000');
    $html = $call('GET', $P . 'budget-reports/variance?variance_type=unfavorable', [], $mgrA)->getContent();
    $ok(str_contains($html, 'Office') && str_contains($html, $m(-3000)) && !str_contains($html, '>Fuel<'), 'Variance (unfavourable): Office 3 000 over, Fuel not listed');
    $html = $call('GET', $P . 'budget-reports/department', [], $mgrA)->getContent();
    $ok(str_contains($html, 'Operations') && str_contains($html, $m(38000 + $staffCost)), 'Department report: Operations with what it spent');
    $r = $call('GET', $P . 'budget-reports/cashflow?budget_id=' . $dep->id, [], $mgrA);
    $ok($r->getStatusCode() === 200, 'Cash flow projection opens for the budget');
    $html = $call('GET', $P . 'budget-dashboard', [], $mgrA)->getContent();
    $ok(str_contains($html, 'Operations'), 'Budget dashboard opens and shows Operations');
    foreach (['budget', 'budget/create', "budget/{$dep->id}/edit", "budget/{$dep->id}/allocations", "budget/{$dep->id}/allocations-data", "budget-dashboard/{$dep->id}/summary",
                 'budget-sheet', "budget-sheet/{$sheet->id}", 'budget-sheet/month/' . today()->format('Y-m'), 'budget-reports/performance?export=pdf', 'budget-reports/variance?export=pdf',
                 'budget-reports/department?export=pdf', "budget-reports/cashflow?budget_id={$dep->id}&export=pdf"] as $p) {
        $r = $call('GET', $P . $p, [], $mgrA);
        $ok(in_array($r->getStatusCode(), [200, 302], true) && $r->getStatusCode() < 500, "$p opens (" . $r->getStatusCode() . ')');
    }
    $r = $call('GET', $P . "budget-sheet/{$sheet->id}/pdf", [], $mgrA);
    $ok($r->getStatusCode() === 200 && str_starts_with($r->getContent(), '%PDF'), 'the I&E sheet PDF downloads');
    $r = $call('GET', $P . "budget-sheet/{$sheet->id}/excel", [], $mgrA);
    $ok($r->getStatusCode() === 200, 'the I&E sheet Excel downloads');

    // a revision through the review
    $call('POST', $P . "budget/{$dep->id}/revise", ['new_amount' => 700000, 'reason' => 'More trips'], $mgrA);
    $ok($eq($in(fn() => Budget::find($dep->id)->total_amount), 600000), 'a revision without the review is refused');
    $call('POST', $P . "budget/{$dep->id}/revise", ['new_amount' => 700000, 'reason' => 'More trips', 'reviewed' => 1], $mgrA);
    $ok($eq($in(fn() => Budget::find($dep->id)->total_amount), 700000) && $in(fn() => \App\Ledger\Models\BudgetRevision::where('budget_id', $dep->id)->count()) === 1, 'revised to 700 000 through the review, revision logged');
    $call('POST', $P . "budget/{$dep->id}/close", [], $mgrA);
    $ok($in(fn() => Budget::find($dep->id)->status) === 'closed', 'the budget is closed');

    // ---- 7. A second branch sees nothing of the first.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrB);
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrB);
    foreach (['budget', 'budget-sheet', 'budget-line', 'budget-dashboard', 'budget-reports/performance', 'budget-reports/department', 'accounting-reports/budget-vs-actual'] as $p) {
        $html = $call('GET', $P . $p, [], $mgrB)->getContent();
        $ok(!str_contains($html, 'Fuel for Operations') && !str_contains($html, 'Operations ' . $year->name) && !str_contains($html, 'I&amp;E ' . $year->name), "branch B's $p shows nothing of branch A");
    }
    foreach (["budget/{$dep->id}", "budget-sheet/{$sheet->id}", "budget/{$dep->id}/allocations"] as $p) {
        $r = $call('GET', $P . $p, [], $mgrB);
        $ok($r->getStatusCode() === 404, "branch B cannot open A's $p (" . $r->getStatusCode() . ')');
    }
    $call('POST', $P . 'budget-line/store', ['code' => 'B-1', 'name' => 'Borrowed', 'section' => 'expenditure', 'location_id' => $ops], $mgrB);
    $ok(!DB::table('budget_lines')->where('code', 'B-1')->exists(), "branch B cannot point a line at A's department");
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
