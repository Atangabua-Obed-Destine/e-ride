<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PaymentBook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * paxhitestbed's IncomeController / ExpenseController: the two were the same
 * controller on the two sides of the sheet, and their views one set of partials
 * (admin/account/partials). Here one base does both; IncomeController and
 * ExpenseController say which side.
 *
 * What changes from paxhitestbed:
 *  - saving goes through the review dialog (the form is marked data-review, the
 *    route carries ledger.review and this refuses without reviewed=1);
 *  - the entry is posted by the sync (PostingRules::forIncome / forExpense), at
 *    once, so the review shows it; a missing mapping never stops the save, the
 *    record is then listed as failed under View transactions and Ledger health;
 *  - editing or deleting a record also moves its payment account movement
 *    (paxhitestbed's gotcha #1), and edits re-post / deletes reverse the entry;
 *  - the school's budget-line headings and budget fields are left out.
 */
abstract class IncomeExpenseController extends LedgerController
{
    /** 'income' or 'expense' */
    abstract protected function side(): string;

    abstract protected function model(): string;

    abstract protected function categoryModel(): string;

    protected function access(): string
    {
        return $this->side();
    }

    protected function base(): array
    {
        $side = $this->side();

        return [
            'title' => __('ledger_accounts.'.$side),
            'route' => 'ledger.'.$side,
            'view' => 'ledger.'.$side,
            'path' => $side,
            'access' => $this->access(),
            'kind' => $side,
        ];
    }

    public function index(Request $request)
    {
        $this->allow($this->access().'-view', $this->access().'-create', $this->access().'-edit', $this->access().'-delete');
        PaymentBook::ensureDefaults();
        $data = $this->base();
        $filter = $this->filter($request);
        $data['selected_title'] = $filter['title'];
        $data['selected_category'] = $filter['categories'];
        $data['window'] = $filter['window'];
        $data['selected_start_date'] = $filter['window']['start'];
        $data['selected_end_date'] = $filter['window']['end'];
        $data['categories'] = ($this->categoryModel())::where('status', 1)->orderBy('name')->get();
        $data['rows'] = $this->rows($filter);
        $data['summary'] = $this->summarise($data['rows']);
        $data['states'] = TransactionMapping::where('transaction_type', $this->side())->whereIn('transaction_id', $data['rows']->pluck('id')->map(fn ($i) => (string) $i))
            ->get(['transaction_id', 'status', 'error', 'journal_entry_id'])->keyBy('transaction_id');

        return view('ledger.account.transactions_index', $data);
    }

    /** The filtered list as a document (paxhitestbed report(): the same filter the screen read, every row). */
    public function report(Request $request)
    {
        $this->allow($this->access().'-view', $this->access().'-create');
        $data = $this->base();
        $filter = $this->filter($request);
        $rows = $this->rows($filter);
        $html = view('ledger.account.report-pdf', $data + ['rows' => $rows, 'summary' => $this->summarise($rows), 'window' => $filter['window'],
            'books' => \App\Ledger\ERide\Books::current(), 'generatedAt' => now()])->render();
        $pdf = $this->getMpdf();
        $pdf->WriteHTML($html);

        return response($pdf->Output('', 'S'), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.\Illuminate\Support\Str::slug($data['title'].' '.($filter['window']['start'] ?? 'all').' '.($filter['window']['end'] ?? '')).'.pdf"']);
    }

    public function create()
    {
        $this->allow($this->access().'-create');
        PaymentBook::ensureDefaults();

        return view('ledger.account.transaction_form', $this->base() + $this->formData(null) + ['mode' => 'create']);
    }

    public function store(Request $request)
    {
        $this->allow($this->access().'-create');
        $this->validateRecord($request);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $class = $this->model();
        try {
            $row = DB::transaction(function () use ($request, $class) {
                $row = new $class();
                $this->fill($row, $request);
                $row->status = 1;
                $row->created_by = auth()->id();
                $row->attach = $this->upload($request);
                $row->save();
                $this->book($row);

                return $row;
            });
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.'.$this->side().'.index'), $this->posted($row, __('ledger_accounts.created')));
    }

    public function show($id)
    {
        $this->allow($this->access().'-view', $this->access().'-create', $this->access().'-edit', $this->access().'-delete');
        $row = ($this->model())::with(['category', 'paymentAccount', 'recordedBy'])->findOrFail($id);

        return view('ledger.account.show', $this->base() + ['row' => $row,
            'state' => TransactionMapping::with('journalEntry')->where('transaction_type', $this->side())->where('transaction_id', (string) $row->id)->first()]);
    }

    public function edit($id)
    {
        $this->allow($this->access().'-edit');
        $row = ($this->model())::with(['category', 'paymentAccount', 'recordedBy'])->findOrFail($id);

        return view('ledger.account.transaction_form', $this->base() + $this->formData($row) + ['mode' => 'edit', 'row' => $row]);
    }

    public function update(Request $request, $id)
    {
        $this->allow($this->access().'-edit');
        $row = ($this->model())::findOrFail($id);
        $this->validateRecord($request, $row);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        try {
            DB::transaction(function () use ($request, $row) {
                $this->fill($row, $request);
                $row->updated_by = auth()->id();
                if ($file = $this->upload($request)) {
                    $this->forget($row->attach);
                    $row->attach = $file;
                }
                $row->save();
                $this->book($row);
            });
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        // back to the list, where the record can be seen (paxhitestbed's note on update())
        return $this->ok(route('ledger.'.$this->side().'.index'), $this->posted($row, __('ledger_accounts.updated')));
    }

    public function destroy(Request $request, $id)
    {
        $this->allow($this->access().'-delete');
        $row = ($this->model())::findOrFail($id);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        DB::transaction(function () use ($row) {
            PaymentBook::remove($this->side(), $row->id);
            $row->delete();
            (new LedgerSync())->record($this->side(), $row->id);
        });
        $this->forget($row->attach);

        return $this->ok(back(), __('ledger_accounts.deleted'));
    }

    // ------------------------------------------------------------------

    protected function validateRecord(Request $request, $row = null): void
    {
        $bid = Ledger::businessId();
        $request->validate([
            'category' => ['required', \Illuminate\Validation\Rule::exists($this->side() === 'income' ? 'income_categories' : 'expense_categories', 'id')
                ->where('business_id', $bid)->whereNull('deleted_at')],
            'title' => 'required|string|max:191',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date|before_or_equal:today',
            'payment_method' => ['required', \Illuminate\Validation\Rule::in(array_keys(PaymentBook::methods()))],
            'payment_account_id' => ['nullable', \Illuminate\Validation\Rule::exists('payment_accounts', 'id')->where('business_id', $bid)],
            'invoice_id' => 'nullable|string|max:191',
            'reference' => 'nullable|string|max:191',
            'note' => 'nullable|string|max:5000',
            // E-Ride: an expense may say its department (the payroll's), for departmental budgets
            'department_id' => ['nullable', \Illuminate\Validation\Rule::exists('departments', 'id')->where('business_id', $bid)],
            // E-Ride: the bus an expense is for, one of these books' fleet
            'vehicle_id' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Ledger\ERide\Books::fleet()))],
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
        ], [], ['category' => __('ledger_accounts.field_category'), 'title' => __('ledger_accounts.field_title'), 'amount' => __('ledger_accounts.field_amount')]);
        // E-Ride: no account chosen, but one payment account sits on the ledger account this way of paying
        // posts to (the safe for cash, the bank for a transfer): the money is that account's, so its book follows
        if (! $request->filled('payment_account_id')) {
            try {
                $ledger = (new \App\Ledger\Services\PostingRules())->moneyAccount(null, $request->payment_method);
                $only = PaymentAccount::where('chart_of_account_id', $ledger)->where('status', 1)->pluck('id');
                // only when its book can take it: otherwise it stays "link it later", as paxhitestbed allows
                if ($only->count() === 1 && ($this->side() === 'income'
                        || PaymentBook::available((int) $only->first(), 'expense', $row->id ?? null) >= round((float) $request->amount, 2) - 0.005)) {
                    $request->merge(['payment_account_id' => (int) $only->first()]);
                }
            } catch (\RuntimeException $e) {
                // the way of paying has no account yet: the posting says so
            }
        }
        // money out of an account needs the money to be there (paxhitestbed: insufficient_balance_in_account)
        if ($this->side() === 'expense' && $request->filled('payment_account_id')) {
            $available = PaymentBook::available((int) $request->payment_account_id, 'expense', $row->id ?? null);
            if ($available < round((float) $request->amount, 2) - 0.005) {
                throw \Illuminate\Validation\ValidationException::withMessages(['payment_account_id' => __('ledger_accounts.insufficient_balance_in_account')
                    .' ('.__('ledger_accounts.field_balance').': '.Ledger::money($available).')']);
            }
        }
    }

    protected function fill($row, Request $request): void
    {
        $row->fill([
            'category_id' => (int) $request->category,
            'title' => $request->title,
            'invoice_id' => $request->invoice_id,
            'amount' => round((float) $request->amount, 2),
            'date' => $request->date,
            'reference' => $request->reference,
            'note' => $request->note,
            'payment_method' => $request->payment_method,
            'payment_account_id' => $request->payment_account_id ?: null,
        ]);
        if ($this->side() === 'expense') {
            $row->department_id = $request->department_id ?: null; // E-Ride: departmental budgets
            $row->vehicle_id = $request->vehicle_id ?: null; // E-Ride: profit by bus
        }
    }

    /** The payment account's book, then the ledger (at once, so the review dialog shows the entry). */
    protected function book($row): void
    {
        $row->loadMissing('category');
        $income = $this->side() === 'income';
        PaymentBook::link($this->side(), $row->id, $row->payment_account_id, $income ? 'credit' : 'debit', (float) $row->amount,
            $row->date->toDateString(), __('ledger_accounts.'.$this->side()).' - '.$row->title,
            __('ledger_accounts.'.$this->side()).': '.($row->category->name ?? '').($row->invoice_id ? ' - '.$row->invoice_id : ''),
            $row->payment_method, $row->reference, auth()->id());
        (new LedgerSync())->record($this->side(), $row->id);
    }

    /** The success message, saying so when the entry could not be posted (the record is saved all the same). */
    protected function posted($row, string $msg): string
    {
        $map = TransactionMapping::where('transaction_type', $this->side())->where('transaction_id', (string) $row->id)->first();
        if ($map && $map->status === 'failed') {
            return $msg.' '.__('ledger_accounts.not_posted_yet', ['why' => $map->error]);
        }

        return $msg;
    }

    protected function formData($row): array
    {
        $class = $this->categoryModel();
        $categories = $class::where('status', 1)->orderBy('name')->get();
        if ($row && ! $categories->contains('id', $row->category_id) && $row->category) {
            $categories->push($row->category); // a category taken out of use stays on its records
        }

        return [
            'categories' => $categories,
            'methods' => PaymentBook::methods(),
            'payment_accounts' => PaymentAccount::where('status', 1)->orderBy('title')->get(),
            'account_help' => 'payment_account_help_text_'.$this->side(),
            // E-Ride: the departments an expense may be spent for (none: the field is not shown)
            'departments' => $this->side() === 'expense' ? \App\Ledger\Services\BudgetLineChoices::locations() : collect(),
            // E-Ride: the buses an expense may be for (none: the field is not shown)
            'fleet' => $this->side() === 'expense' ? \App\Ledger\ERide\Books::fleet() : [],
        ];
    }

    protected function filter(Request $request): array
    {
        $cats = array_values(array_filter(array_map('intval', (array) $request->input('category', []))));

        return [
            'title' => $request->filled('title') ? (string) $request->title : null,
            'categories' => $cats,
            'window' => $this->window($request->input('start_date'), $request->input('end_date'), $request->boolean('all')),
        ];
    }

    protected function rows(array $filter)
    {
        $w = $filter['window'];

        return ($this->model())::with(['category', 'recordedBy', 'paymentAccount'])
            ->when($w['start'], fn ($q) => $q->whereDate('date', '>=', $w['start']))
            ->when($w['end'], fn ($q) => $q->whereDate('date', '<=', $w['end']))
            ->when($filter['title'], fn ($q) => $q->where('title', 'like', '%'.$filter['title'].'%'))
            ->when($filter['categories'], fn ($q) => $q->whereIn('category_id', $filter['categories']))
            ->orderByDesc('date')->orderByDesc('id')->get();
    }

    /** The band above the table: how much, over how many rows, and by payment method (biggest first). */
    protected function summarise($rows): array
    {
        $methods = [];
        foreach ($rows as $row) {
            $key = $row->payment_method ?: '';
            $methods[$key] ??= ['label' => PaymentBook::methodLabel($row->payment_method), 'count' => 0, 'total' => 0.0];
            $methods[$key]['count']++;
            $methods[$key]['total'] += (float) $row->amount;
        }
        uasort($methods, fn ($a, $b) => $b['total'] <=> $a['total']);

        return ['count' => $rows->count(), 'total' => (float) $rows->sum('amount'), 'methods' => $methods];
    }

    /**
     * The period the screen opens on (paxhitestbed TransactionWindow): the fiscal year the
     * books are keeping; an explicit start/end is kept, and "all" shows every record.
     */
    protected function window($start, $end, bool $all): array
    {
        $today = Carbon::today();
        $current = FiscalYear::forDate($today->toDateString());
        $previous = $current ? FiscalYear::where('end_date', '<', $current->start_date)->orderByDesc('end_date')->first() : null;
        $ranges = [['label' => __('ledger_accounts.this_month'), 'start' => $today->copy()->startOfMonth()->toDateString(), 'end' => $today->toDateString()]];
        foreach (array_filter([$current, $previous]) as $fy) {
            $ranges[] = ['label' => $fy->name, 'start' => Carbon::parse($fy->start_date)->toDateString(), 'end' => Carbon::parse($fy->end_date)->toDateString()];
        }
        $ranges[] = ['label' => __('ledger_accounts.all_time'), 'start' => null, 'end' => null];
        if ($all) {
            $start = $end = null;
        } elseif (! $start && ! $end) {
            $start = $current ? Carbon::parse($current->start_date)->toDateString() : $today->copy()->startOfYear()->toDateString();
            $end = $current ? Carbon::parse($current->end_date)->toDateString() : $today->toDateString();
        }
        $start = $start ? Carbon::parse($start)->toDateString() : null;
        $end = $end ? Carbon::parse($end)->toDateString() : null;
        $label = null;
        foreach ($ranges as &$r) {
            $r['active'] = $r['start'] === $start && $r['end'] === $end;
            if ($r['active']) {
                $label = $r['label'];
            }
        }
        unset($r);

        return ['start' => $start, 'end' => $end, 'ranges' => $ranges,
            'label' => $label ?: (($start ? Carbon::parse($start)->format('d/m/Y') : '...').' - '.($end ? Carbon::parse($end)->format('d/m/Y') : '...'))];
    }

    /** An attachment, kept on the public disk under ledger/<side>/<books>. */
    protected function upload(Request $request): ?string
    {
        if (! $request->hasFile('attach')) {
            return null;
        }

        return $request->file('attach')->store('ledger/'.$this->side().'/'.Ledger::businessId(), 'public');
    }

    protected function forget(?string $file): void
    {
        if ($file) {
            Storage::disk('public')->delete($file);
        }
    }

    public static function fileUrl(?string $file): ?string
    {
        return $file ? Storage::disk('public')->url($file) : null;
    }
}
