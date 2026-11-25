<?php
/*
 * paxhitestbed's Income & Expense and Payment Accounts on a bus branch's books, end to end through
 * the real routes (HTTP kernel, middleware, controllers, review), all rolled back: categories and
 * their mappings, payment accounts with an opening balance, an income and an expense through the
 * review (refused without it), an edit that re-posts and a delete that reverses, a supplier bill
 * approved then paid in two parts (one payment voided), a fund transfer edited, a manual deposit,
 * every resulting ledger balance and book balance, a second branch walled off, every page opening.
 *
 * Run: php tests/Ledger/accounts_test.php
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use App\Ledger\Ledger;
use App\Ledger\Models\{ChartOfAccount, Expense, Income, JournalEntry, JournalEntryLine, PaymentAccount, SupplierBill, SupplierPayment, TransactionMapping};
use App\Ledger\Services\{LedgerSync, PostingRules};
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
    $mgrA = accTestUser('Accounts Manager A'); $mgrB = accTestUser('Accounts Manager B');
    $mk = function (string $name, User $mgr) {
        BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => $name, 'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)), 'origin_city' => 'Bamenda',
            'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => []]);
        $a = BusAgency::where('name', $name)->first();
        BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
        return $a;
    };
    $A = $mk('Accounts Branch Bamenda', $mgrA);
    $B = $mk('Accounts Branch Kumba', $mgrB);

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
    $errs = function () use ($session) {
        $e = $session->get('errors');
        return $e ? implode(' | ', $e->all()) : '';
    };
    $P = '/partner/accounting/';

    // ---- 1. Open and set up branch A's books.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrA);
    $bidA = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $A->id)->value('id');
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrA);
    $ok($bidA > 0 && Ledger::isSetUp($bidA), 'branch A books opened and set up');
    $in = fn(callable $fn) => Ledger::forBusiness($bidA, $fn);
    $acc = fn(string $code) => (int)$in(fn() => ChartOfAccount::where('account_code', $code)->value('id'));
    $bal = fn(string $code) => round((float)$in(fn() => JournalEntryLine::where('account_id', ChartOfAccount::where('account_code', $code)->value('id'))
        ->whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->selectRaw('COALESCE(SUM(debit - credit), 0) as b')->value('b')), 2);
    $book = fn(string $title) => round((float)$in(fn() => PaymentAccount::where('title', $title)->value('current_balance')), 2);

    // ---- 2. Categories, and their accounts under Mapping settings.
    $r = $call('GET', $P . 'account/income-category', [], $mgrA);
    $ok($r->getStatusCode() === 200, 'income categories page opens (' . $r->getStatusCode() . ')');
    $call('POST', $P . 'account/income-category', ['title' => 'Parcel commissions'], $mgrA);
    $call('POST', $P . 'account/expense-category', ['title' => 'Fuel'], $mgrA);
    $call('POST', $P . 'account/expense-category', ['title' => 'Office supplies'], $mgrA);
    $call('POST', $P . 'account/expense-category', ['title' => 'Fuel'], $mgrA);
    [$catParcel, $catFuel, $catOffice] = $in(fn() => [\App\Ledger\Models\IncomeCategory::where('name', 'Parcel commissions')->value('id'),
        \App\Ledger\Models\ExpenseCategory::where('name', 'Fuel')->value('id'), \App\Ledger\Models\ExpenseCategory::where('name', 'Office supplies')->value('id')]);
    $ok($catParcel && $catFuel && $catOffice, 'categories created through the routes');
    $ok($in(fn() => \App\Ledger\Models\ExpenseCategory::where('name', 'Fuel')->count()) === 1, 'a second "Fuel" is refused (unique per books)');
    $html = $call('GET', $P . 'mappings/settings', [], $mgrA)->getContent();
    $ok(str_contains($html, 'Parcel commissions') && str_contains($html, 'Office supplies'), 'Mapping settings lists the income and expense categories');
    $call('POST', $P . 'mappings/save-default', ['mappings' => [
        ['mapping_type' => 'income_category', 'category_key' => (string)$catParcel, 'account_id' => $acc('707')],
        ['mapping_type' => 'expense_category', 'category_key' => (string)$catFuel, 'account_id' => $acc('6053')],
        ['mapping_type' => 'expense_category', 'category_key' => (string)$catOffice, 'account_id' => $acc('6055')],
    ]], $mgrA);
    $ok($in(fn() => \App\Ledger\Services\DefaultAccount::id('income_category', (string)$catParcel)) === $acc('707'), 'income category mapped to 707 through Mapping settings');

    // ---- 3. Payment accounts (review), with an opening balance set against 4711.
    $call('GET', $P . 'payment-account', [], $mgrA); // the account types are made on first use
    $cashType = $in(fn() => \App\Ledger\Models\PaymentAccountType::where('kind', 'cash')->value('id'));
    $mobileType = $in(fn() => \App\Ledger\Models\PaymentAccountType::where('kind', 'mobile_money')->value('id'));
    $ok($cashType && $mobileType, 'the three kinds of payment account exist');
    $till = ['title' => 'Main till', 'account_type_id' => $cashType, 'chart_of_account_id' => $acc('571'), 'opening_balance' => 200000, 'contra_account_id' => $acc('4711')];
    $call('POST', $P . 'payment-account/store', $till, $mgrA);
    $ok(!$in(fn() => PaymentAccount::where('title', 'Main till')->exists()), 'payment account without the review tick is refused');
    $call('POST', $P . 'payment-account/store', $till + ['reviewed' => 1], $mgrA);
    $call('POST', $P . 'payment-account/store', ['title' => 'MTN agency line', 'account_type_id' => $mobileType, 'chart_of_account_id' => $acc('5521'), 'opening_balance' => 0, 'reviewed' => 1], $mgrA);
    [$tillId, $mtnId] = $in(fn() => [PaymentAccount::where('title', 'Main till')->value('id'), PaymentAccount::where('title', 'MTN agency line')->value('id')]);
    $ok($tillId && $mtnId, 'two payment accounts created');
    $ok($book('Main till') == 200000 && $eq($bal('571'), 200000) && $eq($bal('4711'), -200000), 'opening balance: book 200 000, Dr 571 / Cr 4711');

    // ---- 4. An income, through the review.
    $income = ['category' => $catParcel, 'title' => 'Parcel desk week 1', 'amount' => 25000, 'date' => today()->toDateString(), 'payment_method' => 'cash', 'payment_account_id' => $tillId, 'invoice_id' => 'PC-001'];
    $r = $call('POST', $P . 'review/dry-run', $income + ['_token' => 'tok', '_review_target' => $P . 'account/income', '_review_method' => 'POST'], $mgrA, true);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'ap-preview') && str_contains($r->getContent(), '707') && str_contains($r->getContent(), '571'), 'review dialog shows the income entry (571 / 707)');
    $ok(!$in(fn() => Income::where('title', 'Parcel desk week 1')->exists()) && $book('Main till') == 200000, 'the review saved nothing');
    $call('POST', $P . 'account/income', $income, $mgrA);
    $ok(!$in(fn() => Income::where('title', 'Parcel desk week 1')->exists()), 'income without the review tick is refused');
    $call('POST', $P . 'account/income', $income + ['reviewed' => 1], $mgrA);
    $incomeId = $in(fn() => Income::where('title', 'Parcel desk week 1')->value('id'));
    $ok($incomeId && $eq($bal('571'), 225000) && $eq($bal('707'), -25000), 'income posted: Dr 571 / Cr 707 (25 000)');
    $ok($book('Main till') == 225000, 'and it is on the till\'s book (225 000)');
    $map = $in(fn() => TransactionMapping::where('transaction_type', PostingRules::INCOME)->where('transaction_id', (string)$incomeId)->first());
    $ok($map && $map->status === 'posted' && $map->fingerprint, 'the income has its mapping (posted, fingerprinted)');

    // ---- 5. An expense paid by MTN, then corrected: more, and from the till.
    $call('POST', $P . 'account/expense', ['category' => $catFuel, 'title' => 'Diesel', 'amount' => 30000, 'date' => today()->toDateString(), 'payment_method' => 'momo'], $mgrA);
    $ok(!$in(fn() => Expense::where('title', 'Diesel')->exists()), 'expense without the review tick is refused');
    $call('POST', $P . 'account/expense', ['category' => $catFuel, 'title' => 'Diesel', 'amount' => 30000, 'date' => today()->toDateString(), 'payment_method' => 'momo', 'reviewed' => 1], $mgrA);
    $expId = $in(fn() => Expense::where('title', 'Diesel')->value('id'));
    $ok($expId && $eq($bal('6053'), 30000) && $eq($bal('5521'), -30000), 'expense posted: Dr 6053 / Cr 5521 (the MTN method)');
    $call('PUT', $P . "account/expense/$expId", ['category' => $catFuel, 'title' => 'Diesel', 'amount' => 35000, 'date' => today()->toDateString(), 'payment_method' => 'cash', 'payment_account_id' => $tillId, 'reviewed' => 1], $mgrA);
    $ok($eq($bal('6053'), 35000) && $eq($bal('5521'), 0) && $eq($bal('571'), 190000), 'edit re-posts: Dr 6053 35 000 / Cr 571, 5521 back to 0');
    $ok($in(fn() => JournalEntry::where('reference_type', PostingRules::EXPENSE)->where('is_reversed', true)->count()) === 1, 'the old expense entry is reversed, not deleted');
    $ok($book('Main till') == 190000, 'the till\'s book follows the edit (190 000)');
    $call('PUT', $P . "account/expense/$expId", ['category' => $catFuel, 'title' => 'Diesel', 'amount' => 999999, 'date' => today()->toDateString(), 'payment_method' => 'cash', 'payment_account_id' => $tillId, 'reviewed' => 1], $mgrA);
    $ok($in(fn() => Expense::find($expId)->amount) == 35000, 'an expense larger than the account holds is refused');

    // ---- 6. Deleting the income reverses it.
    $call('DELETE', $P . "account/income/$incomeId", [], $mgrA);
    $ok($in(fn() => Income::whereKey($incomeId)->exists()), 'delete without the review tick is refused');
    $call('DELETE', $P . "account/income/$incomeId", ['reviewed' => 1], $mgrA);
    $ok(!$in(fn() => Income::whereKey($incomeId)->exists()) && $eq($bal('707'), 0) && $eq($bal('571'), 165000), 'delete reverses: 707 back to 0, 571 = 165 000');
    $ok($book('Main till') == 165000, 'and the till\'s book drops it (165 000)');

    // ---- 7. A fund transfer, then corrected.
    $tr = ['from_account_id' => $tillId, 'to_account_id' => $mtnId, 'amount' => 60000, 'transfer_date' => today()->toDateString()];
    $call('POST', $P . 'payment-account-transfer/store', $tr, $mgrA);
    $ok(!$in(fn() => \App\Ledger\Models\PaymentAccountTransfer::exists()), 'transfer without the review tick is refused');
    $call('POST', $P . 'payment-account-transfer/store', $tr + ['reviewed' => 1], $mgrA);
    $trId = $in(fn() => \App\Ledger\Models\PaymentAccountTransfer::value('id'));
    $ok($trId && $eq($bal('571'), 105000) && $eq($bal('5521'), 60000), 'transfer posted: Dr 5521 / Cr 571 (60 000)');
    $ok($book('Main till') == 105000 && $book('MTN agency line') == 60000, 'both books moved (105 000 / 60 000)');
    $call('POST', $P . 'payment-account-transfer/store', ['amount' => 500000, 'reviewed' => 1] + $tr, $mgrA);
    $ok($in(fn() => \App\Ledger\Models\PaymentAccountTransfer::count()) === 1, 'a transfer above the sending balance is refused');

    // ---- 8. A supplier bill, approved through the review, paid in two parts.
    $call('POST', $P . 'account/suppliers', ['title' => 'Bamenda Tyres', 'payment_terms_days' => 30], $mgrA);
    $supId = $in(fn() => \App\Ledger\Models\ItemSupplier::where('title', 'Bamenda Tyres')->value('id'));
    $ok((bool)$supId, 'supplier added from the bill form');
    $call('POST', $P . 'account/supplier-bills', ['supplier_id' => $supId, 'kind' => 'bill', 'supplier_reference' => 'BT-778', 'bill_date' => today()->toDateString(),
        'vat_amount' => 11550, 'lines' => [['expense_category_id' => $catFuel, 'description' => 'Diesel drums', 'amount' => 50000], ['expense_category_id' => $catOffice, 'amount' => 10000]], 'after' => 'draft'], $mgrA);
    $bill = $in(fn() => SupplierBill::where('supplier_reference', 'BT-778')->first());
    $ok($bill && $bill->isDraft() && $eq($bill->total, 71550) && $eq($bal('401'), 0), 'draft bill saved (71 550 with VAT), nothing posted');
    $ok($bill && $bill->due_date->toDateString() === today()->addDays(30)->toDateString(), 'due date from the supplier\'s 30-day terms');
    $r = $call('GET', $P . "account/supplier-bills/{$bill->id}/preview/approve", [], $mgrA, true);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'ap-preview') && str_contains($r->getContent(), '4452'), 'approval review shows the entry with 4452');
    $ok($in(fn() => SupplierBill::find($bill->id)->isDraft()), 'the approval review approved nothing');
    $call('POST', $P . "account/supplier-bills/{$bill->id}/approve", [], $mgrA);
    $ok($in(fn() => SupplierBill::find($bill->id)->isDraft()), 'approval without the review tick is refused');
    $call('POST', $P . "account/supplier-bills/{$bill->id}/approve", ['reviewed' => 1], $mgrA);
    $bill = $in(fn() => SupplierBill::find($bill->id));
    $ok($bill->isApproved() && $bill->journal_entry_id, 'bill approved with its entry');
    $ok($eq($bal('401'), -71550) && $eq($bal('4452'), 11550) && $eq($bal('6053'), 85000) && $eq($bal('6055'), 10000), 'approved: Dr 6053 50 000 + 6055 10 000 + 4452 11 550 / Cr 401 71 550');
    $pay1 = ['supplier_id' => $supId, 'payment_date' => today()->toDateString(), 'amount' => 40000, 'payment_method' => 'cash', 'payment_account_id' => $tillId,
        'cash_account_id' => $acc('571'), 'allocations' => [$bill->id => 40000], 'reference' => 'RC-1'];
    $call('POST', $P . 'account/supplier-payments', $pay1, $mgrA);
    $ok(!$in(fn() => SupplierPayment::exists()), 'payment without the review tick is refused');
    $call('POST', $P . 'account/supplier-payments', $pay1 + ['reviewed' => 1], $mgrA);
    $ok($eq($bal('401'), -31550) && $eq($bal('571'), 65000) && $book('Main till') == 65000, 'first part: Dr 401 / Cr 571 (40 000), till book 65 000');
    $call('POST', $P . 'account/supplier-payments', ['supplier_id' => $supId, 'payment_date' => today()->toDateString(), 'amount' => 31550, 'payment_method' => 'momo',
        'payment_account_id' => $mtnId, 'cash_account_id' => $acc('5521'), 'allocations' => [$bill->id => 31550], 'reviewed' => 1], $mgrA);
    $ok($eq($bal('401'), 0) && $eq($bal('5521'), 28450) && $book('MTN agency line') == 28450, 'second part: Dr 401 / Cr 5521 (31 550), 401 settled');
    $ok($in(fn() => app(\App\Ledger\Services\SupplierPayables::class)->paymentState(SupplierBill::find($bill->id))) === 'paid', 'the bill reads paid');
    $call('POST', $P . 'account/supplier-payments', ['supplier_id' => $supId, 'payment_date' => today()->toDateString(), 'amount' => 1000, 'cash_account_id' => $acc('571'), 'reviewed' => 1], $mgrA);
    $ok($in(fn() => SupplierPayment::count()) === 2, 'paying more than is owed is refused');
    // void the second payment: the bill is owed again
    $pay2 = $in(fn() => SupplierPayment::orderByDesc('id')->first());
    $r = $call('GET', $P . "account/supplier-payments/{$pay2->id}/preview/void", [], $mgrA, true);
    $ok($r->getStatusCode() === 200 && str_contains($r->getContent(), 'ap-preview'), 'void review renders');
    $call('POST', $P . "account/supplier-payments/{$pay2->id}/void", ['void_reason' => 'Paid twice by mistake', 'reviewed' => 1], $mgrA);
    $ok($in(fn() => SupplierPayment::find($pay2->id)->isVoid()) && $eq($bal('401'), -31550) && $eq($bal('5521'), 60000) && $book('MTN agency line') == 60000,
        'voided payment: reversed, 401 owes 31 550 again, MTN book back to 60 000');
    $call('POST', $P . "account/supplier-bills/{$bill->id}/void", ['void_reason' => 'x', 'reviewed' => 1], $mgrA);
    $ok($in(fn() => SupplierBill::find($bill->id)->isApproved()), 'a bill with a live payment cannot be voided');
    $aging = $in(fn() => app(\App\Ledger\Services\SupplierPayables::class)->aging());
    $ok($eq($aging['totals']['total'], 31550) && $eq($aging['difference'], 0), 'payables aging: 31 550 owed, agrees with the ledger');

    // ---- 9. Correcting the transfer re-posts it; a manual deposit with its other side posts, and its delete reverses.
    $call('PUT', $P . "payment-account-transfer/$trId/update", ['amount' => 70000, 'reviewed' => 1] + $tr, $mgrA);
    $ok($eq($bal('571'), 55000) && $eq($bal('5521'), 70000) && $book('Main till') == 55000 && $book('MTN agency line') == 70000, 'transfer edited to 70 000: re-posted, both books follow');
    $call('POST', $P . "payment-account/$tillId/deposit", ['amount' => 10000, 'transaction_date' => today()->toDateString(), 'title' => 'Capital from the owner', 'contra_account_id' => $acc('101'), 'reviewed' => 1], $mgrA);
    $ok($eq($bal('571'), 65000) && $eq($bal('101'), -10000) && $book('Main till') == 65000, 'deposit with its other side: Dr 571 / Cr 101');
    $dep = $in(fn() => \App\Ledger\Models\PaymentAccountTransaction::where('title', 'Capital from the owner')->first());
    $call('DELETE', $P . "payment-account/transaction/{$dep->id}/delete", ['reviewed' => 1], $mgrA);
    $ok($eq($bal('571'), 55000) && $eq($bal('101'), 0) && $book('Main till') == 55000, 'deleting the deposit reverses it');
    $call('POST', $P . "payment-account/$tillId/withdraw", ['amount' => 5000, 'transaction_date' => today()->toDateString(), 'title' => 'Book-only withdrawal', 'reviewed' => 1], $mgrA);
    $ok($eq($bal('571'), 55000) && $book('Main till') == 50000, 'a withdrawal with no other side moves only the book (as in paxhitestbed)');

    // ---- 10. Everything balances; Cash accounts compares the books; the sweep has nothing to do.
    $in(function () use ($ok) {
        $bad = JournalEntry::where('is_posted', true)->get()->filter(fn($e) => abs($e->lines()->sum('debit') - $e->lines()->sum('credit')) > 0.005)->count();
        $ok($bad === 0, 'every posted entry balances');
        $tb = JournalEntryLine::whereHas('journalEntry', fn($q) => $q->where('is_posted', true))->selectRaw('SUM(debit) d, SUM(credit) c')->first();
        $ok(abs($tb->d - $tb->c) < 0.01, 'trial balance: debits = credits');
        LedgerSync::flush();
        $stats = (new LedgerSync())->sweep();
        $ok(empty($stats['posted']) && empty($stats['failed']) && empty($stats['reposted']) && empty($stats['reversed']), 'sweep finds nothing to post again (' . json_encode($stats) . ')');
    });
    $html = $call('GET', $P . 'cash-accounts', [], $mgrA)->getContent();
    $ok(str_contains($html, 'Main till') && str_contains($html, 'MTN agency line'), 'Cash accounts lists the payment accounts and their books');
    $html = $call('GET', $P . 'mappings/transactions?type=supplier_payment', [], $mgrA)->getContent();
    $ok(str_contains($html, 'SP-' . today()->year), 'View transactions lists the supplier payments');

    // ---- 11. Every page of the two areas opens.
    $expenseId = $expId; $billId = $bill->id; $payId = $pay2->id;
    $pages = ['account/income', 'account/income/create', 'account/income-category', 'account/expense', 'account/expense/create', "account/expense/$expenseId",
        "account/expense/$expenseId/edit", 'account/expense-category', 'account/supplier-bills', 'account/supplier-bills/create', "account/supplier-bills/$billId",
        'account/supplier-payments', "account/supplier-payments/create?supplier_id=$supId", "account/supplier-payments/$payId", 'account/supplier-bills/aging',
        "account/supplier-bills/statement?supplier_id=$supId", 'payment-account', 'payment-account/create', "payment-account/$tillId/edit", "payment-account/$tillId/account-book",
        "payment-account/$tillId/deposit", "payment-account/$tillId/withdraw", 'payment-account-transfer', 'payment-account-transfer/create', "payment-account-transfer/$trId/edit",
        'payment-account-report/cashflow', "payment-account-report/statement/$tillId", 'payment-account-report/summary', 'payment-account-report/unlinked',
        'account/expense/report', 'mappings/transactions?type=income'];
    foreach ($pages as $p) {
        $r = $call('GET', $P . $p, [], $mgrA);
        $ok($r->getStatusCode() === 200, "page $p opens (" . $r->getStatusCode() . ')');
    }

    // ---- 12. A second branch never sees the first's records.
    $call('GET', '/partner/bus-agency/accounting', [], $mgrB);
    $bidB = (int)DB::table('ledger_businesses')->where('owner_type', 'bus_agency')->where('owner_id', $B->id)->value('id');
    $call('POST', $P . 'setup', ['start' => 'today', 'reviewed' => 1], $mgrB);
    $ok($bidB && $bidB !== $bidA && Ledger::isSetUp($bidB), 'branch B opens its own books');
    foreach (['account/expense', 'payment-account', 'account/supplier-bills', 'account/supplier-payments', 'payment-account-transfer', 'payment-account-report/cashflow', 'account/expense-category'] as $p) {
        $html = $call('GET', $P . $p, [], $mgrB)->getContent();
        $ok(!str_contains($html, 'Diesel') && !str_contains($html, 'Main till') && !str_contains($html, 'Bamenda Tyres') && !str_contains($html, 'Office supplies'), "branch B's $p shows nothing of branch A");
    }
    foreach (["account/expense/$expenseId", "account/supplier-bills/$billId", "payment-account/$tillId/account-book", "account/supplier-payments/$payId"] as $p) {
        $r = $call('GET', $P . $p, [], $mgrB);
        $ok($r->getStatusCode() === 404, "branch B cannot open A's $p (" . $r->getStatusCode() . ')');
    }
    $call('PUT', $P . "account/expense/$expenseId", ['category' => $catFuel, 'title' => 'Hijack', 'amount' => 1, 'date' => today()->toDateString(), 'payment_method' => 'cash', 'reviewed' => 1], $mgrB);
    $ok($in(fn() => Expense::find($expenseId)->title) === 'Diesel', "branch B cannot change A's expense");
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
