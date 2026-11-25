<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAccAccount;
use Modules\BusManagement\Entities\BusAccDocument;
use Modules\BusManagement\Entities\BusAccEntry;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAccAsset;
use Modules\BusManagement\Entities\BusAccEmployee;
use Modules\BusManagement\Entities\BusAccPayslip;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompany;
use Modules\BusManagement\Entities\BusCompanyStaff;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Entities\BusVehicle;
use Modules\BusManagement\Service\Accounting\AccountingPreview;
use Modules\BusManagement\Service\Accounting\AccountingService;
use Modules\BusManagement\Service\Accounting\AccReports;
use Modules\BusManagement\Service\Accounting\Ledger;
use Modules\BusManagement\Service\Accounting\LedgerSync;
use Modules\BusManagement\Service\Accounting\PayService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The accounts of a set of books: a branch's (branch portal, manager and accountant) or a head
 * office's own (head office portal, director and accountant; the same screens, the head office
 * layout). Every action that posts shows its preview first (the real action, rolled back) and is
 * refused unless the preview was reviewed and ticked.
 */
class AccountsController extends Controller
{
    public function __construct(protected Ledger $ledger, protected LedgerSync $sync, protected AccountingService $service,
                                protected AccountingPreview $preview, protected AccReports $reports)
    {
    }

    /** The owner of the books shown: the branch, or the head office (company). Shares what the screens need. */
    protected function agency(Request $r): BusAgency|BusCompany
    {
        $company = $this->isCompanyRoute($r);
        if ($company) {
            // Regional managers do not see the money of the company.
            abort_if($r->attributes->get('busCompanyStaff')->role === BusCompanyStaff::REGIONAL, 403);
        }
        $owner = $company ? $r->attributes->get('busCompany') : $r->attributes->get('busAgency');
        view()->share([
            'acc' => $this->ar($r),
            'accLayout' => $company ? 'busmanagement::company.layout' : 'busmanagement::partner.layout',
            'accOwnerName' => $company ? $owner->name . ' · ' . translate('Head office') : $owner->displayName(),
            'isCompanyBooks' => $company,
            'carriesBuses' => $company || !$owner->company_id,
            'canReopen' => $this->canReopen($r),
        ]);
        return $owner;
    }

    protected function isCompanyRoute(Request $r): bool
    {
        return str_starts_with((string)$r->route()?->getName(), 'bus-company.');
    }

    /** The route names of these screens: the branch portal's, or the head office's. */
    protected function ar(Request $r): string
    {
        return $this->isCompanyRoute($r) ? 'bus-company.books' : 'bus-partner.accounts';
    }

    /** Reopening a month or a year: the branch manager, or the director at the head office. */
    protected function canReopen(Request $r): bool
    {
        return $this->isCompanyRoute($r)
            ? (bool)$r->attributes->get('busCompanyStaff')?->isDirector()
            : $r->attributes->get('busStaff')?->role === BusStaff::MANAGER;
    }

    /** The books must be open; otherwise the setup page. */
    protected function books(Request $r)
    {
        $books = $this->ledger->books($this->agency($r));
        if ($books) {
            $this->sync->sync($this->agency($r));
        }
        return $books;
    }

    public function index(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        return view('busmanagement::partner.accounts.overview', ['books' => $books, 'o' => $this->reports->overview($agency)] + $this->formData($agency));
    }

    public function setup(Request $r)
    {
        $agency = $this->agency($r);
        if ($this->ledger->books($agency)) {
            return redirect()->route($this->ar($r));
        }
        return view('busmanagement::partner.accounts.setup', ['company' => $agency instanceof BusAgency ? $agency->company : null,
            'companyBooks' => $agency instanceof BusAgency && $agency->company_id && $this->ledger->books((string)$agency->company_id)]);
    }

    public function spending(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        [$from, $to] = $this->period($r);
        $q = BusAccDocument::with(['account', 'moneyAccount', 'vehicle'])->where('agency_id', $agency->id)->where('type', BusAccDocument::EXPENSE)
            ->whereBetween('date', [$from, $to]);
        if ($r->filled('category')) {
            $q->where('account_id', $r->input('category'));
        }
        if ($r->filled('bus')) {
            $q->where('vehicle_id', $r->input('bus'));
        }
        $expenses = $q->orderByDesc('date')->orderByDesc('number')->paginate(40)->withQueryString();
        $bills = BusAccDocument::with('account')->where('agency_id', $agency->id)->where('type', BusAccDocument::EXPENSE)->where('status', 'posted')
            ->where('owed', '>', 0)->orderByRaw('due_on is null')->orderBy('due_on')->get();
        $payments = BusAccDocument::with(['moneyAccount', 'parent'])->where('agency_id', $agency->id)->where('type', BusAccDocument::BILL_PAYMENT)
            ->orderByDesc('date')->orderByDesc('number')->limit(20)->get();
        return view('busmanagement::partner.accounts.spending', compact('books', 'expenses', 'bills', 'payments', 'from', 'to') + $this->formData($agency));
    }

    public function money(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        $o = $this->reports->overview($agency);
        $docs = BusAccDocument::with(['account', 'moneyAccount', 'toAccount'])->where('agency_id', $agency->id)
            ->whereIn('type', [BusAccDocument::TRANSFER, BusAccDocument::INCOME, BusAccDocument::CHECK, BusAccDocument::BRANCH_MONEY])
            ->orderByDesc('date')->orderByDesc('created_at')->paginate(30)->withQueryString();
        $branchBooks = [];
        if ($agency instanceof BusCompany) {
            foreach (BusAgency::where('company_id', $agency->id)->orderBy('origin_city')->get() as $b) {
                if ($this->ledger->books($b)) {
                    $branchBooks[] = ['branch' => $b, 'accounts' => BusAccAccount::where('agency_id', $b->id)->where('money', true)->where('active', true)
                        ->where('role', '!=', 'counter_cash')->orderBy('code')->get()];
                }
            }
        }
        return view('busmanagement::partner.accounts.money', compact('books', 'o', 'docs', 'branchBooks') + $this->formData($agency));
    }

    /** Reconcile a bank or Mobile Money account: the movements not matched yet, to tick against the statement. */
    public function reconcile(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        $account = BusAccAccount::where('agency_id', $agency->id)->where('money', true)->where('role', '!=', 'counter_cash')->find((string)$r->input('account'));
        if (!$account) {
            return redirect()->route($this->ar($r) . '.money')->withErrors(['account' => translate('Choose an account from the list.')]);
        }
        try {
            $date = Carbon::parse($r->input('date', today()->toDateString()))->min(today())->toDateString();
        } catch (\Throwable) {
            $date = today()->toDateString();
        }
        $lines = $this->service->unmatched($account, $date);
        $book = $this->ledger->balance($account, $date);
        $last = BusAccDocument::where('agency_id', $agency->id)->where('type', BusAccDocument::CHECK)->where('status', 'posted')
            ->where('account_id', $account->id)->orderByDesc('date')->orderByDesc('created_at')->first();
        $statement = $r->filled('counted') ? (float)$r->input('counted') : null;
        return view('busmanagement::partner.accounts.reconcile', compact('books', 'account', 'date', 'lines', 'book', 'last', 'statement'));
    }

    public function cash(Request $r)
    {
        $agency = $this->agency($r);
        abort_if($agency instanceof BusCompany, 404);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        $holding = $this->service->agentsHolding($agency);
        $counts = BusAccDocument::with(['toAccount', 'staffUser', 'creator'])->where('agency_id', $agency->id)->where('type', BusAccDocument::CASH_COUNT)
            ->orderByDesc('created_at')->paginate(30);
        return view('busmanagement::partner.accounts.cash', compact('books', 'holding', 'counts') + $this->formData($agency));
    }

    /** The buses carried in these books: value, wear so far, value left; add one, sell or retire one. */
    public function buses(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        $assets = $this->reports->assets($agency);
        $inBooks = BusAccAsset::where('status', 'active')->pluck('vehicle_id')->map(fn($id) => (string)$id)->all();
        return view('busmanagement::partner.accounts.buses', compact('books', 'assets', 'inBooks') + $this->formData($agency));
    }

    protected function pay(): PayService
    {
        return app(PayService::class);
    }

    /** Staff pay: the people paid, the monthly payrolls, and what is owed to CNPS, the State and in VAT. */
    public function staffPay(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        $employees = BusAccEmployee::where('agency_id', $agency->id)->orderByDesc('active')->orderBy('name')->get();
        $payrolls = BusAccDocument::where('agency_id', $agency->id)->where('type', BusAccDocument::PAYROLL)->orderByDesc('date')->orderByDesc('created_at')->limit(24)->get();
        $owed = $this->pay()->owedToAuthorities($agency);
        $rates = $this->pay()->employerRates($agency);
        $vatOn = $this->pay()->vatOn($agency);
        $remittances = BusAccDocument::with('moneyAccount')->where('agency_id', $agency->id)->whereIn('type', [BusAccDocument::REMITTANCE, BusAccDocument::VAT_RETURN])
            ->orderByDesc('date')->orderByDesc('created_at')->limit(20)->get();
        return view('busmanagement::partner.accounts.pay', compact('books', 'employees', 'payrolls', 'owed', 'rates', 'vatOn', 'remittances') + $this->formData($agency));
    }

    public function saveEmployee(Request $r, ?string $employee = null)
    {
        $agency = $this->agency($r);
        $model = $employee ? BusAccEmployee::where('agency_id', $agency->id)->findOrFail($employee) : null;
        $this->pay()->saveEmployee($agency, $r->only(['name', 'job', 'base_pay', 'cnps_number', 'niu', 'phone', 'hired_on', 'active']), $model);
        return back()->with('success', $model ? translate('Saved.') : translate('Added to the staff.'));
    }

    /** One payslip, ready to print. */
    public function payslip(Request $r, int $payslip)
    {
        $agency = $this->agency($r);
        $slip = BusAccPayslip::with(['employee', 'document'])->where('agency_id', $agency->id)->findOrFail($payslip);
        return view('busmanagement::partner.accounts.payslip', ['slip' => $slip, 'owner' => $agency]);
    }

    /** The budget of a year: an amount per income and spending account and month. */
    public function budget(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        $year = (int)$r->input('year', today()->year);
        $year = max($books->starts_on->year, min(today()->year + 1, $year));
        $accounts = $this->pay()->budgetAccounts($agency);
        $budget = $this->pay()->budget($agency, $year);
        return view('busmanagement::partner.accounts.budget', compact('books', 'year', 'accounts', 'budget'));
    }

    public function saveBudget(Request $r)
    {
        $agency = $this->agency($r);
        $this->pay()->saveBudget($agency, (int)$r->input('year'), (array)$r->input('amounts', []));
        return back()->with('success', translate('Budget saved.'));
    }

    public function reports(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        [$from, $to] = $this->period($r);
        $tab = in_array($r->input('tab'), ['income', 'profit', 'cash', 'budget', 'trial', 'balance'], true) ? $r->input('tab') : 'income';
        $by = in_array($r->input('by'), ['bus', 'route', 'trip'], true) ? $r->input('by') : 'bus';
        $scope = $agency instanceof BusCompany && $r->input('scope') === 'all' ? 'all' : 'own';
        if ($scope === 'all' && in_array($tab, ['cash', 'budget'], true)) {
            $tab = 'income';
        }
        $data = $scope === 'all' ? $this->reports->combined($agency, $tab, $from, $to, $by) : match ($tab) {
            'income' => $this->reports->incomeStatement($agency, $from, $to),
            'profit' => $this->reports->profit($agency, $from, $to, $by),
            'cash' => $this->reports->cashFlow($agency, $from, $to),
            'budget' => $this->reports->budgetVsActual($agency, $from, $to),
            'trial' => $this->reports->trialBalance($agency, $from, $to),
            'balance' => $this->reports->balanceSheet($agency, $to),
        };
        if ($r->boolean('csv')) {
            return $this->csv($agency, $tab, $data, $from, $to, $by);
        }
        return view('busmanagement::partner.accounts.reports', compact('books', 'tab', 'by', 'data', 'from', 'to', 'scope'));
    }

    public function journal(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        [$from, $to] = $this->period($r);
        $accounts = $this->reports->accounts($agency);
        $account = $r->filled('account') ? $accounts->first(fn($a) => (string)$a->id === (string)$r->input('account')) : null;
        $agent = $r->filled('agent') ? (string)$r->input('agent') : null;
        $ledger = $account ? $this->reports->ledger($account, $from, $to, $agent) : null;
        $entries = null;
        if (!$account) {
            $q = BusAccEntry::with('lines.account')->where('agency_id', $agency->id)->whereBetween('date', [$from, $to]);
            if ($r->filled('q')) {
                $s = '%' . str_replace(['%', '_'], ['\%', '\_'], $r->input('q')) . '%';
                $q->where(fn($w) => $w->where('memo', 'like', $s)->orWhere('number', 'like', $s));
            }
            if ($r->filled('journal')) {
                $q->where('journal', $r->input('journal'));
            }
            $entries = $q->orderByDesc('date')->orderByDesc('number')->paginate(30)->withQueryString();
        }
        $docs = BusAccDocument::where('agency_id', $agency->id)->whereIn('id', collect($entries?->items() ?? [])->where('source_type', 'document')->pluck('source_id'))
            ->get()->keyBy(fn($d) => (string)$d->id);
        return view('busmanagement::partner.accounts.journal', compact('books', 'from', 'to', 'accounts', 'account', 'ledger', 'entries', 'agent', 'docs') + $this->formData($agency));
    }

    public function document(Request $r, string $document)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        $doc = BusAccDocument::with(['account', 'moneyAccount', 'toAccount', 'parent', 'vehicle', 'destination', 'staffUser', 'creator'])
            ->where('agency_id', $agency->id)->findOrFail($document);
        $accounts = $this->reports->accounts($agency)->keyBy(fn($a) => (string)$a->id);
        $entries = BusAccEntry::with('lines')->whereIn('id', array_filter([$doc->entry_id, $doc->void_entry_id, $doc->auto_reverse_entry_id]))->orderBy('number')->get()
            ->map(fn($e) => $this->preview->entry($e, $accounts));
        $payments = $doc->type === BusAccDocument::EXPENSE ? BusAccDocument::with('moneyAccount')->where('parent_id', $doc->id)->orderBy('date')->get() : collect();
        $payslips = $doc->type === BusAccDocument::PAYROLL ? BusAccPayslip::where('document_id', $doc->id)->orderBy('id')->get() : collect();
        return view('busmanagement::partner.accounts.document', compact('books', 'doc', 'entries', 'payments', 'payslips') + $this->formData($agency));
    }

    public function settings(Request $r)
    {
        $agency = $this->agency($r);
        if (!$books = $this->books($r)) {
            return redirect()->route($this->ar($r) . '.setup');
        }
        $accounts = $this->reports->accounts($agency);
        $balances = $this->preview->balances($agency);
        $months = [];
        $m = $books->starts_on->copy()->startOfMonth();
        $rows = DB::table('bus_acc_months')->where('agency_id', $agency->id)->get()->keyBy(fn($x) => Carbon::parse($x->month)->format('Y-m'));
        while ($m->lte(today())) {
            $months[] = ['month' => $m->copy(), 'row' => $rows[$m->format('Y-m')] ?? null, 'over' => $m->copy()->endOfMonth()->lt(today())];
            $m->addMonth();
        }
        $months = array_reverse($months);
        $yearRows = DB::table('bus_acc_years')->where('agency_id', $agency->id)->get()->keyBy('year');
        $years = [];
        for ($y = today()->year; $y >= $books->starts_on->year; $y--) {
            $years[] = ['year' => $y, 'row' => $yearRows[$y] ?? null, 'over' => $y < today()->year];
        }
        $headOfficeCloses = $agency instanceof BusAgency && $agency->company_id;
        return view('busmanagement::partner.accounts.settings', compact('books', 'accounts', 'balances', 'months', 'years', 'headOfficeCloses'));
    }

    public function saveAccount(Request $r, ?string $account = null)
    {
        $agency = $this->agency($r);
        $model = $account ? BusAccAccount::where('agency_id', $agency->id)->findOrFail($account) : null;
        $this->service->saveAccount($agency, $r->only(['code', 'name', 'type', 'money', 'active']), $model);
        return back()->with('success', $model ? translate('Account saved.') : translate('Account added.'));
    }

    public function saveSettings(Request $r)
    {
        $this->service->saveSettings($this->agency($r), $r->only(['refunds_from', 'eride_from', 'vat_registered', 'vat_on_tickets', 'vat_rate', 'pay_rates']));
        return back()->with('success', translate('Saved.'));
    }

    // ------------------------------------------------------------------ preview and confirm

    public const ACTIONS = ['open', 'expense', 'pay', 'income', 'transfer', 'count', 'check', 'journal', 'void', 'close', 'reopen',
        'asset', 'dispose', 'branchmoney', 'closeyear', 'reopenyear', 'payroll', 'staffpay', 'remit', 'vatreturn'];

    public function previewAction(Request $r, string $action)
    {
        abort_unless(in_array($action, self::ACTIONS, true), 404);
        $agency = $this->agency($r);
        [$title, $confirm, $run, $extra] = $this->action($r, $agency, $action);
        $out = $this->preview->run($agency, $title, $run, $extra);
        $out['confirm'] = $confirm;
        return response()->json($out);
    }

    public function doAction(Request $r, string $action)
    {
        abort_unless(in_array($action, self::ACTIONS, true), 404);
        $agency = $this->agency($r);
        if (!$r->boolean('reviewed')) {
            return response()->json(['message' => translate('Look at the preview and tick it first.')], 422);
        }
        [$title, , $run] = $this->action($r, $agency, $action);
        try {
            $result = DB::transaction(fn() => $run());
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        }
        $redirect = $result instanceof BusAccDocument && !in_array($action, ['open'], true)
            ? route($this->ar($r) . '.document', $result->id) : null;
        $message = match ($action) {
            'open' => translate('The books are open.'),
            'closeyear' => translate('The year is closed.'),
            'payroll' => translate('Payroll posted. Pay the staff from its page.'),
            'reopenyear' => translate('The year is open again.'),
            'void' => translate('Voided: the entry was reversed.'),
            'close' => translate('The month is closed.'),
            'reopen' => translate('The month is open again.'),
            default => translate('Recorded and posted.'),
        };
        return response()->json(['ok' => true, 'message' => $message, 'redirect' => $redirect]);
    }

    /** [title, tick text, the action, extra preview] for each action. */
    protected function action(Request $r, BusAgency|BusCompany $agency, string $action): array
    {
        $uid = (string)auth()->id();
        $data = $r->except(['_token', 'reviewed']);
        $doc = fn() => BusAccDocument::where('agency_id', $agency->id)->findOrFail((string)$r->input('document'));
        $company = $agency instanceof BusCompany;
        abort_if($company && in_array($action, ['count'], true), 404);
        abort_if(!$company && $action === 'branchmoney', 404);
        $mustReopen = function () use ($r) {
            if (!$this->canReopen($r)) {
                throw ValidationException::withMessages(['month' => $this->isCompanyRoute($r) ? translate('Only the director reopens.') : translate('Only the manager reopens a month.')]);
            }
        };
        return match ($action) {
            'open' => [translate('Open the books'), $company ? translate('The start date and the balances match what the head office really has on that day.')
                : translate('The start date and the balances match what the branch really has on that day.'),
                fn() => $this->service->open($agency, $data, $uid), function (&$out) use ($company) {
                    $out['notes'][] = $company ? translate('What the head office holds for each branch is taken from the branch books. The rest balances against Owner capital.')
                        : translate('Tickets already paid for trips still ahead, refunds owed and E-Ride statements not paid are added from the bus records. The rest balances against Owner capital.');
                }],
            'asset' => [translate('Add a bus to the books'), translate('The value, the years and the value at the end are the ones the owner agreed.'),
                fn() => $this->service->addAsset($agency, $data, $uid), function (&$out, $d) {
                    if ($d instanceof BusAccDocument && ($a = BusAccAsset::where('document_id', $d->id)->first())) {
                        $out['summary'][] = [translate('Wear each month'), \Modules\BusManagement\Lib\AccFormat::n($a->monthly()) . ' FCFA'];
                        $out['summary'][] = [translate('Worn down by'), $a->lastMonth()->translatedFormat('F Y')];
                        $out['notes'][] = translate('The wear of each month is posted on its own on the 1st of the next month.');
                    }
                }],
            'dispose' => [translate('Sell or retire a bus'), translate('I checked the date, and the price against the sale papers.'),
                fn() => $this->service->disposeAsset($agency, BusAccAsset::where('agency_id', $agency->id)->findOrFail((string)$r->input('asset')), $data, $uid), null],
            'branchmoney' => [translate('Money with a branch'), translate('The money left one side and reached the other, for this amount.'),
                fn() => $this->service->branchMoney($agency, $data, $uid), function (&$out) {
                    $out['notes'][] = translate('Posted in the head office books and in the branch books together.');
                }],
            'payroll' => [translate('Payroll'), translate('I checked each person, their pay this month and any extra, against the attendance and the contracts.'),
                fn() => $this->pay()->payroll($agency, $data, $uid), function (&$out, $d) {
                    if (!$d instanceof BusAccDocument) {
                        return;
                    }
                    $n = fn($v) => \Modules\BusManagement\Lib\AccFormat::n($v);
                    $rows = BusAccPayslip::where('document_id', $d->id)->orderBy('id')->get()->map(fn($p) => [
                        $p->detail['name'] ?? '', $n($p->gross), $n($p->cnps), $n($p->irpp + $p->cac), $n($p->cfc + $p->crtv + $p->tdl), $n($p->net), $n($p->employer_cnps + $p->employer_taxes),
                    ])->all();
                    $out['effects'][] = ['title' => translate('Payslips'), 'columns' => [translate('Person'), translate('Gross'), 'CNPS', translate('IRPP and CAC'),
                        translate('CFC, CRTV, TDL'), translate('Net to pay'), translate('Employer charges')], 'numeric' => [1, 2, 3, 4, 5, 6], 'rows' => $rows];
                    $out['notes'][] = translate('The withholdings follow the DGI scale (IRPP, CAC, CFC, CRTV) and CNPS rules. The TDL bands and the employer rates come from Settings: confirm them with your accountant.');
                }],
            'staffpay' => [translate('Pay the staff'), translate('The net pay of each person left the account chosen, for this total.'),
                fn() => $this->pay()->payStaff($agency, $doc(), $data, $uid), null],
            'remit' => [translate('Pay CNPS, the State or VAT'), translate('I paid this amount and I have the receipt.'),
                fn() => $this->pay()->remit($agency, $data, $uid), null],
            'vatreturn' => [translate('VAT return'), translate('These figures match the VAT return I file for this month.'),
                fn() => $this->pay()->vatReturn($agency, $data, $uid), null],
            'closeyear' => [translate('Close the year'), translate('Every month of the year is checked and closed. Nothing more can be dated in it.'),
                fn() => $this->service->closeYear($agency, (int)$r->input('year'), $uid), function (&$out, $res) use ($agency, $r) {
                    foreach ($res['results'] ?? [] as $x) {
                        $out['summary'][] = [$x['books'], translate('Result') . ' ' . \Modules\BusManagement\Lib\AccFormat::n($x['result']) . ' FCFA'];
                    }
                    $this->yearChecklist($agency, (int)$r->input('year'), $out);
                }],
            'reopenyear' => [translate('Reopen the year'), translate('I know why it must be reopened, and I will close it again after the correction.'),
                function () use ($agency, $r, $uid, $mustReopen) {
                    $mustReopen();
                    return $this->service->reopenYear($agency, (int)$r->input('year'), (string)$r->input('reason'), $uid);
                }, null],
            'expense' => [translate('Record an expense'), translate('I checked the amount, the category and where it was paid from against the receipt.'),
                fn() => $this->service->expense($agency, $data, $uid), null],
            'pay' => [translate('Pay a bill'), translate('I checked the bill and the amount paid against the payment proof.'),
                fn() => $this->service->payBill($agency, $doc(), $data, $uid), null],
            'income' => [translate('Record other income'), translate('I checked the amount, the category and where the money came in.'),
                fn() => $this->service->income($agency, $data, $uid), null],
            'transfer' => [translate('Move money'), translate('The money left the first account and reached the second, for this amount.'),
                fn() => $this->service->transfer($agency, $data, $uid), null],
            'count' => [translate('Count an agent cash'), translate('I counted the cash myself, with the agent, and put it where it says.'),
                fn() => $this->service->cashCount($agency, $data, $uid), null],
            'check' => [translate('Check a statement'), translate('The balance is the one printed on the statement for this date.'),
                fn() => $this->service->check($agency, $data, $uid), null],
            'journal' => [translate('Journal entry'), translate('I checked every line, and the debits equal the credits.'),
                fn() => $this->service->journal($agency, $data, $uid), null],
            'void' => [translate('Void'), translate('I understand: the entry is reversed and the document stays, marked void.'),
                fn() => $this->service->void($agency, $doc(), (string)$r->input('reason'), $uid), null],
            'close' => [translate('Close the month'), translate('I checked this month. Nothing more can be posted in it once closed.'),
                fn() => $this->service->closeMonth($agency, (string)$r->input('month'), $uid), fn(&$out) => $this->closePreview($agency, (string)$r->input('month'), $out)],
            'reopen' => [translate('Reopen the month'), translate('I know why it must be reopened, and I will close it again after the correction.'),
                function () use ($agency, $r, $uid, $mustReopen) {
                    $mustReopen();
                    return $this->service->reopenMonth($agency, (string)$r->input('month'), (string)$r->input('reason'), $uid);
                }, null],
        };
    }

    /**
     * Before a year closes: every bus's wear posted up to December, every bank and Mobile Money
     * account that moved in December reconciled in December, the books agreeing with the records.
     */
    protected function yearChecklist(BusAgency|BusCompany $owner, int $year, array &$out): void
    {
        $dec = \Illuminate\Support\Carbon::create($year, 12, 1);
        foreach ($this->service->yearBooks($owner) as $o) {
            $name = $o instanceof BusCompany ? translate('Head office') : $o->branchLabel();
            foreach (BusAccAsset::where('agency_id', $o->id)->whereIn('status', ['active', 'sold', 'retired'])->get() as $a) {
                $lastDue = $a->lastMonth()->min($dec);
                if ($a->starts_on->lte($lastDue->copy()->endOfMonth()) && ($a->status === 'active' || ($a->disposed_on && $a->disposed_on->gt($lastDue->copy()->endOfMonth())))
                    && !BusAccEntry::where('agency_id', $o->id)->where('source_key', 'wear:' . $a->id . ':' . $lastDue->format('Y-m'))->exists()) {
                    $out['warnings'][] = translate(key: '{books}: the wear of {bus} for {month} is not posted yet.', replace: ['books' => $name, 'bus' => $a->name, 'month' => $lastDue->translatedFormat('F Y')]);
                }
            }
            $checked = BusAccDocument::where('agency_id', $o->id)->where('type', BusAccDocument::CHECK)->where('status', 'posted')
                ->whereBetween('date', [$dec->toDateString(), $dec->copy()->endOfMonth()->toDateString()])->pluck('account_id')->map(fn($id) => (string)$id);
            $moved = BusAccLine::query()->from('bus_acc_lines as l')->join('bus_acc_accounts as a', 'a.id', '=', 'l.account_id')
                ->where('l.agency_id', $o->id)->where('a.money', true)->where('a.role', '!=', 'counter_cash')
                ->whereBetween('l.date', [$dec->toDateString(), $dec->copy()->endOfMonth()->toDateString()])->distinct()->pluck('a.id')->map(fn($id) => (string)$id);
            $unchecked = BusAccAccount::whereIn('id', $moved->diff($checked))->get();
            if ($unchecked->isNotEmpty()) {
                $out['warnings'][] = translate(key: '{books}: not reconciled in December: {list}.', replace: ['books' => $name, 'list' => $unchecked->map->label()->implode(', ')]);
            }
            foreach ($this->reports->health($o) as $h) {
                $out['warnings'][] = $name . ': ' . $h;
            }
        }
        if (empty($out['warnings'])) {
            $out['notes'][] = translate('Checklist: every month closed, bus wear posted, accounts reconciled in December, books agreeing with the records.');
        }
    }

    /** The month at a glance in the close preview, and what is worth a look before closing. */
    protected function closePreview(BusAgency|BusCompany $agency, string $month, array &$out): void
    {
        $m = Carbon::parse($month)->startOfMonth();
        $is = $this->reports->incomeStatement($agency, $m->toDateString(), $m->copy()->endOfMonth()->toDateString());
        $out['summary'] = [
            [translate('Month'), $m->translatedFormat('F Y')],
            [translate('Income'), \Modules\BusManagement\Lib\AccFormat::n($is['total_income']) . ' FCFA'],
            [translate('Spending'), \Modules\BusManagement\Lib\AccFormat::n($is['total_spending']) . ' FCFA'],
            [translate('Result'), \Modules\BusManagement\Lib\AccFormat::n($is['result']) . ' FCFA'],
        ];
        $holding = collect($agency instanceof BusAgency ? $this->service->agentsHolding($agency) : [])->where('held', '>', 0);
        if ($holding->isNotEmpty()) {
            $out['warnings'][] = translate(key: 'Counter agents still hold cash not counted: {list}. Count it first if it belongs to this month.',
                replace: ['list' => $holding->map(fn($h) => $h['name'] . ' ' . \Modules\BusManagement\Lib\AccFormat::n($h['held']))->implode(', ')]);
        }
        $checked = BusAccDocument::where('agency_id', $agency->id)->where('type', BusAccDocument::CHECK)->where('status', 'posted')
            ->whereBetween('date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->pluck('account_id')->map(fn($id) => (string)$id);
        $moved = BusAccLine::query()->from('bus_acc_lines as l')->join('bus_acc_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.agency_id', $agency->id)->where('a.money', true)->where('a.role', '!=', 'counter_cash')
            ->whereBetween('l.date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->distinct()->pluck('a.id')->map(fn($id) => (string)$id);
        $unchecked = BusAccAccount::whereIn('id', $moved->diff($checked))->get();
        if ($unchecked->isNotEmpty()) {
            $out['warnings'][] = translate(key: 'Not reconciled with a statement this month: {list}.', replace: ['list' => $unchecked->map->label()->implode(', ')]);
        }
        $open = BusDeparture::where('agency_id', $agency->id)->whereBetween('date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])
            ->whereIn('status', BusDeparture::OPEN)->count();
        if ($open > 0) {
            $out['notes'][] = translate(key: '{n} trips of this month are not marked departed yet: their tickets become sales when they are.', replace: ['n' => $open]);
        }
    }

    // ------------------------------------------------------------------ helpers

    /** Lists for the forms: categories, money accounts, buses, routes, agents, recent trips. */
    protected function formData(BusAgency|BusCompany $agency): array
    {
        $accounts = BusAccAccount::where('agency_id', $agency->id)->where('active', true)->orderBy('code')->get();
        view()->share('vatOn', (bool)$this->ledger->books($agency)?->setting('vat_registered'));
        if ($agency instanceof BusCompany) {
            return [
                'expenseCats' => $accounts->filter(fn($a) => $a->type === 'expense' && $a->category)->values(),
                'incomeCats' => $accounts->filter(fn($a) => $a->type === 'revenue' && $a->category)->values(),
                'moneyAccounts' => $accounts->filter(fn($a) => $a->money)->values(),
                'allAccounts' => $accounts,
                'buses' => BusVehicle::where('company_id', $agency->id)->orderBy('registration')->get(['id', 'registration', 'name', 'agency_id']),
                'routes' => collect(), 'agents' => collect(), 'trips' => collect(),
            ];
        }
        return [
            'expenseCats' => $accounts->filter(fn($a) => $a->type === 'expense' && $a->category)->values(),
            'incomeCats' => $accounts->filter(fn($a) => $a->type === 'revenue' && $a->category)->values(),
            'moneyAccounts' => $accounts->filter(fn($a) => $a->money)->values(),
            'allAccounts' => $accounts,
            'buses' => BusVehicle::usableByAgency($agency)->orderBy('registration')->get(['bus_vehicles.id', 'registration', 'name']),
            'routes' => BusDestination::where('agency_id', $agency->id)->orderBy('city')->get(['id', 'city']),
            'agents' => BusStaff::with('user')->where('agency_id', $agency->id)->get()->filter(fn($s) => $s->user)
                ->map(fn($s) => ['id' => (string)$s->user_id, 'name' => trim($s->user->first_name . ' ' . $s->user->last_name), 'role' => $s->role, 'active' => $s->active])->values(),
            'trips' => BusDeparture::with('destination')->where('agency_id', $agency->id)->whereBetween('date', [today()->subDays(14)->toDateString(), today()->addDays(3)->toDateString()])
                ->where('status', '!=', BusDeparture::CANCELLED)->orderByDesc('date')->orderByDesc('time')->limit(80)->get(),
        ];
    }

    /** The period asked (from, to), this month by default. */
    protected function period(Request $r): array
    {
        try {
            $from = $r->filled('from') ? Carbon::parse($r->input('from'))->toDateString() : today()->startOfMonth()->toDateString();
            $to = $r->filled('to') ? Carbon::parse($r->input('to'))->toDateString() : today()->toDateString();
        } catch (\Throwable) {
            [$from, $to] = [today()->startOfMonth()->toDateString(), today()->toDateString()];
        }
        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    protected function csv(BusAgency|BusCompany $agency, string $tab, array $data, string $from, string $to, string $by): StreamedResponse
    {
        $name = 'accounts-' . $tab . '-' . $from . '-' . $to . '.csv';
        return response()->streamDownload(function () use ($tab, $data, $by) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $n = fn($v) => number_format((float)$v, 2, '.', '');
            switch ($tab) {
                case 'income':
                    fputcsv($out, [translate('Account'), translate('Name'), translate('Amount')]);
                    foreach ($data['income'] as $row) {
                        fputcsv($out, [$row['account']->code, $row['account']->displayName(), $n($row['amount'])]);
                    }
                    fputcsv($out, ['', translate('Total income'), $n($data['total_income'])]);
                    foreach ($data['spending'] as $row) {
                        fputcsv($out, [$row['account']->code, $row['account']->displayName(), $n(-$row['amount'])]);
                    }
                    fputcsv($out, ['', translate('Total spending'), $n(-$data['total_spending'])]);
                    fputcsv($out, ['', translate('Result'), $n($data['result'])]);
                    break;
                case 'profit':
                    fputcsv($out, [translate($by === 'bus' ? 'Bus' : ($by === 'route' ? 'Route' : 'Trip')), translate('Ticket and other income'), translate('Fuel'), translate('Other spending'), translate('E-Ride commission'), translate('Profit')]);
                    foreach ($data as $row) {
                        fputcsv($out, [$row['label'] ?? translate('Not linked'), $n($row['income']), $n($row['fuel']), $n($row['other']), $n($row['commission']), $n($row['profit'])]);
                    }
                    break;
                case 'cash':
                    fputcsv($out, ['', translate('Amount')]);
                    fputcsv($out, [translate('Money at the start'), $n($data['opening'])]);
                    foreach ($data['rows'] as $row) {
                        fputcsv($out, [translate($row['label']), $n($row['amount'])]);
                    }
                    fputcsv($out, [translate('Money at the end'), $n($data['closing'])]);
                    break;
                case 'budget':
                    fputcsv($out, [translate('Account'), translate('Name'), translate('Budget'), translate('Actual'), translate('Difference')]);
                    foreach (array_merge($data['rows']['income'], $data['rows']['spending']) as $row) {
                        fputcsv($out, [$row['account']->code, $row['account']->displayName(), $n($row['budget']), $n($row['actual']), $n($row['diff'])]);
                    }
                    break;
                case 'trial':
                    fputcsv($out, [translate('Account'), translate('Name'), translate('Opening debit'), translate('Opening credit'), translate('Debit'), translate('Credit'), translate('Closing debit'), translate('Closing credit')]);
                    foreach ($data['rows'] as $row) {
                        fputcsv($out, [$row['account']->code, $row['account']->displayName(), $n($row['od']), $n($row['oc']), $n($row['d']), $n($row['c']), $n($row['cd']), $n($row['cc'])]);
                    }
                    $t = $data['totals'];
                    fputcsv($out, ['', translate('Total'), $n($t['od']), $n($t['oc']), $n($t['d']), $n($t['c']), $n($t['cd']), $n($t['cc'])]);
                    break;
                case 'balance':
                    fputcsv($out, [translate('Section'), translate('Account'), translate('Name'), translate('Amount')]);
                    foreach ([['has', 'What the branch has'], ['owes', 'What the branch owes'], ['equity', 'Owner']] as [$k, $label]) {
                        foreach ($data[$k] as $row) {
                            fputcsv($out, [translate($label), $row['account']->code, $row['account']->displayName(), $n($row['amount'])]);
                        }
                    }
                    fputcsv($out, [translate('Owner'), '', translate('Result since the start'), $n($data['result'])]);
                    break;
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
