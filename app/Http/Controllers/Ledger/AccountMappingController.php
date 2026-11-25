<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\LedgerHealth;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PostingRules;
use App\Ledger\Services\Preview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Transaction mappings (paxhitestbed Admin\AccountMappingController):
 *
 *   Mapping settings   which ledger account each kind of POS document uses
 *   View transactions  every POS document and payment, what it posted, and "re-post"
 *
 * paxhitestbed maps fee, income, expense and payroll categories; PosInnova
 * maps sales, purchases, expense categories, payment methods and the rest.
 * Each mapping row holds one account (PostingRules decides the side), so a
 * row has one account picker where paxhitestbed has a debit and a credit.
 *
 * Saving settings never rewrites what is posted: new documents use the new
 * accounts; moving an old document is the explicit re-post (remap / bulk
 * sync), through the review dialog showing the entries it would write.
 */
class AccountMappingController extends LedgerController
{
    /** The kinds of document the list shows (PostingRules::TYPES and payments). */
    // E-Ride: the bus records that post (PostingRules::TYPES), in place of posinnova's documents and payments
    const LIST_TYPES = PostingRules::TYPES;

    const MAX_ITEMS = 500;

    // ------------------------------------------------------------------ settings

    public function settings()
    {
        $this->allow('transaction-mapping-settings');

        return view('ledger.mappings.settings', [
            'groups' => $this->groups(),
            'accounts' => $this->postableAccounts(),
        ]);
    }

    /**
     * The settings form, grouped as paxhitestbed's (its fee / income / expense / payroll groups
     * become sales, purchases, expenses, payment methods and other). Each row:
     * type, key, label, hint (what it posts), account (current), configured.
     */
    private function groups(): array
    {
        $have = DefaultAccountMapping::get()->keyBy(fn ($m) => $m->mapping_type.'|'.$m->category_key);
        $row = function (string $type, string $key, string $label, string $hint, ?string $note = null) use ($have) {
            $m = $have->get($type.'|'.$key);

            return [
                'type' => $type, 'key' => $key, 'label' => $label, 'hint' => $hint, 'note' => $note,
                'account' => $m ? ($m->debit_account_id ?: $m->credit_account_id) : null,
                'configured' => $m && $m->status,
                'off' => $m && ! $m->status,
            ];
        };
        $L = fn ($k) => __('ledger_map.'.$k);

        $groups = [];
        // E-Ride (bus): what the bus records post (App\Ledger\Services\PostingRules), in place of posinnova's sales.
        $groups['tickets'] = ['title' => $L('group_tickets'), 'icon' => 'fa-ticket-alt', 'color' => 'primary', 'rows' => [
            $row('tickets_held', '', __('ledger.map_tickets_held'), $L('hint_tickets_held')),
            $row('ticket_sales', '', __('ledger.map_ticket_sales'), $L('hint_ticket_sales')),
            $row('refunds_owed', '', __('ledger.map_refunds_owed'), $L('hint_refunds_owed')),
            $row('refunds_after_trip', '', __('ledger.map_refunds_after_trip'), $L('hint_refunds_after_trip')),
            $row('vat_collected', '', __('ledger.map_vat_collected'), $L('hint_vat_collected')),
            $row('gateway_fees', '', __('ledger.map_gateway_fees'), $L('hint_gateway_fees')),
            $row('till_short', '', __('ledger.map_till_short'), $L('hint_till_short')),
            $row('till_over', '', __('ledger.map_till_over'), $L('hint_till_over')),
        ]];
        $groups['eride'] = ['title' => $L('group_eride'), 'icon' => 'fa-handshake', 'color' => 'info', 'rows' => [
            $row('eride_commission', '', __('ledger.map_eride_commission'), $L('hint_eride_commission')),
            $row('eride_payable', '', __('ledger.map_eride_payable'), $L('hint_eride_payable')),
            $row('head_office', '', __('ledger.map_head_office'), $L('hint_head_office')),
        ]];
        $groups['purchases'] = ['title' => $L('group_purchases'), 'icon' => 'fa-truck', 'color' => 'success', 'rows' => [
            $row('suppliers', '', __('ledger.map_suppliers'), $L('hint_suppliers')),
            $row('supplier_advances', '', __('ledger.map_supplier_advances'), $L('hint_supplier_advances')),
            $row('vat_deductible', '', __('ledger.map_vat_deductible'), $L('hint_vat_deductible')),
        ]];
        $expense = [];
        foreach ($this->expenseCategories() as $c) {
            $expense[] = $row('expense_category', (string) $c->id, $c->name, $L('hint_expense_category'), $L('note_category_fallback'));
        }
        $expense[] = $row('expense_category', '', $L('any_other_category'), $L('hint_expense_category'));
        $expense[] = $row('expense_payable', '', __('ledger.map_expense_payable'), $L('hint_expense_payable'));
        $groups['expenses'] = ['title' => $L('group_expenses'), 'icon' => 'fa-receipt', 'color' => 'danger', 'rows' => $expense];
        // E-Ride: paxhitestbed's income categories (Income & Expense), built as the expense ones
        $income = [];
        foreach ($this->incomeCategories() as $c) {
            $income[] = $row('income_category', (string) $c->id, $c->name, __('ledger_accounts.hint_income_category'), $L('note_category_fallback'));
        }
        $income[] = $row('income_category', '', $L('any_other_category'), __('ledger_accounts.hint_income_category'));
        $groups['income'] = ['title' => __('ledger_accounts.group_income'), 'icon' => 'fa-hand-holding-usd', 'color' => 'success', 'rows' => $income];
        $methods = [];
        foreach (LedgerHealth::paymentMethods() as $method => $label) {
            $methods[] = $row('payment_method', $method, $label, $L('hint_payment_method'));
        }
        $groups['methods'] = ['title' => $L('group_methods'), 'icon' => 'fa-money-bill-alt', 'color' => 'info', 'rows' => $methods,
            'help' => $L('methods_help')];
        $groups['other'] = ['title' => $L('group_other'), 'icon' => 'fa-cogs', 'color' => 'warning', 'rows' => [
            $row('customer_advances', '', __('ledger.map_customer_advances'), $L('hint_customer_advances')),
            $row('opening_balance', '', __('ledger.map_opening_balance'), $L('hint_opening_balance')),
            $row('transfers', '', __('ledger.map_transfers'), $L('hint_transfers'), $L('note_not_used_yet')),
            $row('retained_earnings', '', __('ledger.map_retained_earnings'), $L('hint_retained_earnings')),
            $row('income_summary', '', __('ledger.map_income_summary'), $L('hint_income_summary')),
        ]];
        // E-Ride: paxhitestbed's payroll and tax (App\Ledger\Payroll\PayrollAccounts; its mappings 'payroll' and 'payroll_tax')
        $groups['payroll'] = ['title' => __('ledger_payroll.group_payroll'), 'icon' => 'fa-user-tie', 'color' => 'primary', 'rows' => array_map(
            fn ($type) => $row($type, '', __('ledger_payroll.map_'.$type), __('ledger_payroll.hint_map_'.$type)),
            \App\Ledger\Payroll\PayrollAccounts::TYPES)];

        return $groups;
    }

    /** Which (type, key) pairs the form may save. */
    private function allowedKeys(): array
    {
        $ok = [];
        foreach ($this->groups() as $g) {
            foreach ($g['rows'] as $r) {
                $ok[$r['type'].'|'.$r['key']] = true;
            }
        }

        return $ok;
    }

    public function saveDefaultMappings(Request $request)
    {
        $this->allow('transaction-mapping-settings');
        $request->validate([
            'mappings' => 'required|array',
            'mappings.*.mapping_type' => 'required|string|max:40',
            'mappings.*.category_key' => 'nullable|string|max:60',
            'mappings.*.account_id' => 'nullable|integer',
        ]);
        $allowed = $this->allowedKeys();
        $postable = ChartOfAccount::postable()->pluck('id')->flip();
        $saved = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->input('mappings') as $m) {
                $type = (string) $m['mapping_type'];
                $key = (string) ($m['category_key'] ?? '');
                $acc = isset($m['account_id']) && $m['account_id'] !== '' ? (int) $m['account_id'] : null;
                if (! isset($allowed[$type.'|'.$key])) {
                    $errors[] = __('ledger_map.err_unknown_mapping', ['type' => $type]);

                    continue;
                }
                $row = DefaultAccountMapping::where('mapping_type', $type)->where('category_key', $key)->first();
                if (! $acc) {
                    // An expense category left blank uses "any other category" again; other rows keep their account.
                    if ($row && in_array($type, ['expense_category', 'income_category'], true) && $key !== '') {
                        $row->delete();
                        $saved++;
                    }

                    continue;
                }
                if (! isset($postable[$acc])) {
                    $errors[] = __('ledger_map.err_not_postable');

                    continue;
                }
                if ($row && (int) $row->debit_account_id === $acc && (int) $row->credit_account_id === $acc && $row->status) {
                    continue;
                }
                $row = $row ?: new DefaultAccountMapping(['mapping_type' => $type, 'category_key' => $key, 'created_by' => auth()->id()]);
                $row->fill(['debit_account_id' => $acc, 'credit_account_id' => $acc, 'status' => 1, 'updated_by' => auth()->id()])->save();
                $saved++;
            }
            if ($errors) {
                DB::rollBack();

                return $this->answer($request, false, implode(' ', array_unique($errors)), route('ledger.mappings.settings'));
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->answer($request, false, __('ledger_map.err_saving', ['msg' => $e->getMessage()]), route('ledger.mappings.settings'));
        }

        return $this->answer($request, true, $saved ? __('ledger_map.saved_mappings') : __('ledger_map.nothing_changed'), route('ledger.mappings.settings'));
    }

    /** paxhitestbed answers the settings form in JSON (AJAX); a plain form post gets a redirect. */
    private function answer(Request $request, bool $ok, string $msg, string $to)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => $ok, 'msg' => $msg, 'message' => $msg], $ok ? 200 : 422);
        }

        return $ok ? $this->ok($to, $msg) : $this->err($to, $msg);
    }

    // ------------------------------------------------------------------ transactions

    public function transactionsList(Request $request)
    {
        $this->allow('transaction-mapping-view');
        $f = $this->filters($request);
        $q = $this->listQuery($f);

        $stats = (clone $q)->selectRaw("COUNT(*) as n,
            SUM(CASE WHEN x.map_status = 'posted' THEN 1 ELSE 0 END) as posted,
            SUM(CASE WHEN x.map_status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN x.map_status IS NULL AND (? IS NULL OR x.date >= ?) THEN 1 ELSE 0 END) as unposted,
            COALESCE(SUM(x.amount), 0) as amount", [$this->postFrom(), $this->postFrom()])->first();

        $rows = $q->orderByDesc('x.date')->orderByDesc('x.id')->paginate(50)->appends($request->query());
        $this->decorate($rows);

        return view('ledger.mappings.transactions', [
            'rows' => $rows,
            'stats' => $stats,
            'f' => $f,
            'types' => self::LIST_TYPES,
            'postFrom' => $this->postFrom(),
            'canManage' => auth()->user()->can('transaction-mapping-manage'),
            'canRemap' => auth()->user()->can('transaction-mapping-remap'),
        ]);
    }

    private function filters(Request $request): array
    {
        $date = function ($v) {
            if (! $v) {
                return null;
            }
            foreach (['Y-m-d', 'd/m/Y'] as $fmt) {
                try {
                    return \Carbon\Carbon::createFromFormat('!'.$fmt, $v)->toDateString();
                } catch (\Throwable $e) {
                }
            }

            return null;
        };
        $type = $request->get('type', 'all');
        $status = $request->get('status', 'all');

        return [
            'type' => in_array($type, self::LIST_TYPES) ? $type : 'all',
            'status' => in_array($status, ['posted', 'failed', 'none', 'unposted', 'history']) ? $status : 'all',
            'date_from' => $date($request->get('date_from')),
            'date_to' => $date($request->get('date_to')),
        ];
    }

    private $postFrom = false;

    private function postFrom(): ?string
    {
        if ($this->postFrom === false) {
            $this->postFrom = AccountingSetting::getValue('post_from_date');
        }

        return $this->postFrom;
    }

    /**
     * Every bus record of the branch that posts, with its mapping (or none yet). E-Ride's version of
     * posinnova's list of POS documents and payments: money moves (tickets paid, refunds, moves to
     * another trip), trips that left, and E-Ride's statements (owed, then paid).
     */
    private function listQuery(array $f)
    {
        $b = Ledger::businessId();
        $row = DB::table('ledger_businesses')->where('id', $b)->first();
        $agency = $row && $row->owner_type === \App\Ledger\ERide\Books::AGENCY ? (string) $row->owner_id : '-';
        $join = function ($q, string $col, string $type) use ($b) {
            return $q->leftJoin('transaction_mappings as m', function ($j) use ($b, $col, $type) {
                $j->on('m.transaction_id', '=', $col)->where('m.transaction_type', $type)->where('m.business_id', $b);
            });
        };
        $cols = ['m.id as map_id', 'm.status as map_status', 'm.journal_entry_id', 'm.error'];
        $parts = [];
        $parts[PostingRules::MOVE] = $join(DB::table('bus_money_moves as mv')->where('mv.agency_id', $agency)->whereNull('mv.skipped'), 'mv.id', PostingRules::MOVE)
            ->leftJoin('bus_bookings as bk', 'bk.id', '=', 'mv.booking_id')
            ->select(array_merge([DB::raw('CAST(mv.id AS CHAR) as id'), DB::raw("'".PostingRules::MOVE."' as type"), DB::raw('DATE(mv.at) as date'), 'mv.amount',
                DB::raw('bk.code as number'), DB::raw('mv.kind as doc_status'), DB::raw('NULL as contact_id'), DB::raw('mv.method as on_id')], $cols));
        $parts[PostingRules::DEPARTURE] = $join(DB::table('bus_departures as d')->where('d.agency_id', $agency)
            ->whereIn('d.status', [\Modules\BusManagement\Entities\BusDeparture::DEPARTED, \Modules\BusManagement\Entities\BusDeparture::CANCELLED]), 'd.id', PostingRules::DEPARTURE)
            // a trip that left holding no ticket money posts nothing: not listed
            ->where(fn ($w) => $w->whereNull('m.status')->orWhere('m.status', '!=', 'none'))
            ->leftJoin('bus_destinations as ds', 'ds.id', '=', 'd.destination_id')
            ->select(array_merge([DB::raw('CAST(d.id AS CHAR) as id'), DB::raw("'".PostingRules::DEPARTURE."' as type"), DB::raw('COALESCE(DATE(d.departed_at), d.date) as date'),
                DB::raw('m.amount as amount'), DB::raw("CONCAT(DATE_FORMAT(d.date, '%d/%m'), ' ', LEFT(COALESCE(d.delayed_to, d.time), 5), ' ', COALESCE(ds.city, '')) as number"),
                DB::raw('d.status as doc_status'), DB::raw('NULL as contact_id'), DB::raw('NULL as on_id')], $cols));
        foreach ([PostingRules::STATEMENT, PostingRules::STATEMENT_PAID] as $kind) {
            $q = $join(DB::table('bus_statements as s')->where('s.agency_id', $agency)->where('s.total', '>', 0), 's.id', $kind);
            if ($kind === PostingRules::STATEMENT_PAID) {
                $q->whereNotNull('s.paid_at');
            }
            $parts[$kind] = $q->select(array_merge([DB::raw('CAST(s.id AS CHAR) as id'), DB::raw("'".$kind."' as type"),
                DB::raw($kind === PostingRules::STATEMENT ? 'LAST_DAY(s.month) as date' : 'DATE(s.paid_at) as date'), DB::raw('s.total as amount'),
                DB::raw("DATE_FORMAT(s.month, '%m/%Y') as number"), 's.status as doc_status', DB::raw('NULL as contact_id'), DB::raw('NULL as on_id')], $cols));
        }
        // E-Ride: paxhitestbed's Income & Expense and Payment Accounts records (on every set of books)
        $own = fn (string $table, string $kind) => $join(DB::table($table.' as r')->where('r.business_id', $b), 'r.id', $kind);
        $parts[PostingRules::INCOME] = $own('incomes', PostingRules::INCOME)->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::INCOME."' as type"),
            'r.date as date', 'r.amount', 'r.title as number', DB::raw('NULL as doc_status'), DB::raw('NULL as contact_id'), 'r.payment_method as on_id'], $cols));
        $parts[PostingRules::EXPENSE] = $own('expenses', PostingRules::EXPENSE)->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::EXPENSE."' as type"),
            'r.date as date', 'r.amount', 'r.title as number', DB::raw('NULL as doc_status'), DB::raw('NULL as contact_id'), 'r.payment_method as on_id'], $cols));
        $parts[PostingRules::SUPPLIER_BILL] = $own('supplier_bills', PostingRules::SUPPLIER_BILL)->where('r.status', '!=', 'draft')
            ->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::SUPPLIER_BILL."' as type"),
                'r.bill_date as date', 'r.total as amount', 'r.number as number', 'r.status as doc_status', DB::raw('NULL as contact_id'), DB::raw('NULL as on_id')], $cols));
        $parts[PostingRules::SUPPLIER_PAYMENT] = $own('supplier_payments', PostingRules::SUPPLIER_PAYMENT)
            ->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::SUPPLIER_PAYMENT."' as type"),
                'r.payment_date as date', 'r.amount', 'r.number as number', DB::raw("IF(r.voided_at IS NULL, NULL, 'void') as doc_status"), DB::raw('NULL as contact_id'), 'r.payment_method as on_id'], $cols));
        $parts[PostingRules::PAYMENT_TRANSFER] = $own('payment_account_transfers', PostingRules::PAYMENT_TRANSFER)
            ->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::PAYMENT_TRANSFER."' as type"),
                'r.transfer_date as date', 'r.amount', DB::raw('NULL as number'), DB::raw('NULL as doc_status'), DB::raw('NULL as contact_id'), DB::raw('NULL as on_id')], $cols));
        $parts[PostingRules::PAYMENT_ACCOUNT_TXN] = $own('payment_account_transactions', PostingRules::PAYMENT_ACCOUNT_TXN)->whereNotNull('r.contra_account_id')
            ->where(fn ($w) => $w->whereNull('r.reference_type')->orWhere('r.reference_type', 'opening_balance'))
            ->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::PAYMENT_ACCOUNT_TXN."' as type"),
                'r.transaction_date as date', 'r.amount', 'r.title as number', 'r.transaction_type as doc_status', DB::raw('NULL as contact_id'), DB::raw('NULL as on_id')], $cols));
        // E-Ride: paxhitestbed's payroll and tax (a payroll's accrual is listed once approved or paid, its payment once paid, or while its entry stands)
        $parts[PostingRules::PAYROLL] = $own('payrolls', PostingRules::PAYROLL)->where(fn ($w) => $w->where('r.status', 1)->orWhereNotNull('r.approved_at')->orWhereNotNull('m.id'))
            ->leftJoin('ledger_staff as st', 'st.id', '=', 'r.staff_id')
            ->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::PAYROLL."' as type"),
                DB::raw('COALESCE(DATE(r.approved_at), r.pay_date, r.salary_month) as date'), 'r.net_salary as amount', DB::raw("CONCAT(COALESCE(st.staff_no, ''), ' ', DATE_FORMAT(r.salary_month, '%m/%Y')) as number"),
                DB::raw('NULL as doc_status'), DB::raw('NULL as contact_id'), 'r.payment_method as on_id'], $cols));
        $parts[PostingRules::PAYROLL_PAYMENT] = $own('payrolls', PostingRules::PAYROLL_PAYMENT)->where(fn ($w) => $w->where('r.status', 1)->orWhereNotNull('m.id'))
            ->leftJoin('ledger_staff as st', 'st.id', '=', 'r.staff_id')
            ->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::PAYROLL_PAYMENT."' as type"),
                DB::raw('COALESCE(r.pay_date, r.salary_month) as date'), 'r.net_salary as amount', DB::raw("CONCAT(COALESCE(st.staff_no, ''), ' ', DATE_FORMAT(r.salary_month, '%m/%Y')) as number"),
                DB::raw('NULL as doc_status'), DB::raw('NULL as contact_id'), 'r.payment_method as on_id'], $cols));
        $parts[PostingRules::STAFF_LOAN] = $own('staff_loans', PostingRules::STAFF_LOAN)
            ->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::STAFF_LOAN."' as type"),
                'r.given_on as date', 'r.amount', 'r.title as number', DB::raw('NULL as doc_status'), DB::raw('NULL as contact_id'), DB::raw('NULL as on_id')], $cols));
        $parts[PostingRules::TAX_REMITTANCE] = $own('tax_remittances', PostingRules::TAX_REMITTANCE)
            ->select(array_merge([DB::raw('CAST(r.id AS CHAR) as id'), DB::raw("'".PostingRules::TAX_REMITTANCE."' as type"),
                'r.payment_date as date', 'r.amount', 'r.reference as number', DB::raw('NULL as doc_status'), DB::raw('NULL as contact_id'), DB::raw('NULL as on_id')], $cols));

        if ($f['type'] !== 'all' && isset($parts[$f['type']])) {
            $union = $parts[$f['type']];
        } else {
            $union = array_shift($parts);
            foreach ($parts as $p) {
                $union->unionAll($p);
            }
        }
        $q = DB::query()->fromSub($union, 'x');
        if ($f['date_from']) {
            $q->where('x.date', '>=', $f['date_from']);
        }
        if ($f['date_to']) {
            $q->where('x.date', '<=', $f['date_to']);
        }
        $from = $this->postFrom();
        switch ($f['status']) {
            case 'posted':
            case 'failed':
            case 'none':
                $q->where('x.map_status', $f['status']);
                break;
            case 'unposted':
                $q->whereNull('x.map_status');
                if ($from) {
                    $q->where('x.date', '>=', $from);
                }
                break;
            case 'history':
                $q->whereNull('x.map_status');
                $from ? $q->where('x.date', '<', $from) : $q->whereRaw('1 = 0');
                break;
        }

        return $q;
    }

    /** Names, links and the state in words, for the rows of one page. (E-Ride: the bus records.) */
    private function decorate($rows): void
    {
        $items = collect($rows->items());
        $entries = JournalEntry::whereIn('id', $items->pluck('journal_entry_id')->filter()->unique())->get(['id', 'entry_number'])->keyBy('id');
        $methods = LedgerHealth::paymentMethods();
        $from = $this->postFrom();

        foreach ($rows->items() as $r) {
            $r->contact = null;
            $r->link = $r->type === PostingRules::DEPARTURE && \Route::has('bus-partner.departures') ? ['url' => route('bus-partner.departures'), 'modal' => false] : null;
            $r->label = trim(\App\Ledger\Services\PaymentBook::docLabel($r->type).' '.($r->number ?: '#'.$r->id));
            $r->reference = match ($r->type) {
                PostingRules::MOVE => implode(' · ', array_filter([__('ledger.move_'.$r->doc_status), $methods[$r->on_id] ?? null])),
                PostingRules::DEPARTURE => __('ledger.trip_'.$r->doc_status),
                // E-Ride: paxhitestbed's Income & Expense and Payment Accounts records
                PostingRules::INCOME, PostingRules::EXPENSE, PostingRules::SUPPLIER_PAYMENT => implode(' · ', array_filter([
                    $r->doc_status ? __('ledger_accounts.state_'.$r->doc_status) : null, $methods[$r->on_id] ?? null])) ?: null,
                PostingRules::SUPPLIER_BILL => __('ledger_accounts.state_'.$r->doc_status),
                PostingRules::PAYMENT_ACCOUNT_TXN => __('ledger_accounts.'.($r->doc_status === 'credit' ? 'deposit' : 'withdraw')),
                default => null,
            };
            $show = [PostingRules::INCOME => 'ledger.income.show', PostingRules::EXPENSE => 'ledger.expense.show',
                PostingRules::SUPPLIER_BILL => 'ledger.supplier-bill.show', PostingRules::SUPPLIER_PAYMENT => 'ledger.supplier-payment.show'][$r->type] ?? null;
            if ($show) {
                $r->link = ['url' => route($show, $r->id), 'modal' => false];
            } elseif ($r->type === PostingRules::PAYMENT_TRANSFER) {
                $r->link = ['url' => route('ledger.payment-account-transfer.index'), 'modal' => false];
            }
            $r->entry = $r->journal_entry_id && isset($entries[$r->journal_entry_id]) ? $entries[$r->journal_entry_id] : null;
            $r->state = $r->map_status ?: (($from && $r->date < $from) ? 'history' : 'unposted');
        }
    }

    // ------------------------------------------------------------------ re-posting

    /** Re-post one document with the current mappings (paxhitestbed updateMapping), through the review. */
    public function remap(Request $request, $mappingId)
    {
        $this->allow('transaction-mapping-remap');
        $map = TransactionMapping::findOrFail((int) $mappingId);
        $items = [[$map->transaction_type, (string) $map->transaction_id]];
        $back = route('ledger.mappings.transactions', $request->only(['type', 'status', 'date_from', 'date_to', 'page']));

        if ($request->get('preview')) {
            return $this->preview($items);
        }
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $done = $this->run($items);

        return $done['failed'] ? $this->err($back, $this->message($done)) : $this->ok($back, $this->message($done));
    }

    /**
     * Sync a selection (paxhitestbed bulkSync): post what was never posted, retry what failed,
     * re-post what posted with accounts that have since changed. With sweep=1, run the catch-up
     * for the whole business (Ledger health's "Sync now").
     */
    public function bulkSync(Request $request)
    {
        $this->allow('transaction-mapping-manage');
        $sweep = $request->boolean('sweep');
        $back = $sweep ? route('ledger.ledger-health') : route('ledger.mappings.transactions', $request->only(['type', 'status', 'date_from', 'date_to', 'page']));
        $items = [];
        if (! $sweep) {
            $request->validate(['items' => 'required|array|min:1|max:'.self::MAX_ITEMS, 'items.*' => 'string|max:40'],
                ['items.required' => __('ledger_map.err_select_some')]);
            foreach (array_unique($request->input('items')) as $it) {
                if (! preg_match('/^([a-z_]+):([0-9a-f-]{1,36})$/', $it, $m) || ! in_array($m[1], self::LIST_TYPES)) {
                    abort(422, __('ledger_map.err_bad_item'));
                }
                $items[] = [$m[1], $m[2]];
            }
        }

        if ($request->get('preview')) {
            return $sweep ? $this->previewSweep() : $this->preview($items);
        }
        if (! $this->reviewed($request)) {
            return $sweep ? $this->err($back, __('ledger.review_first')) : $this->needReview();
        }
        @set_time_limit(600);
        if ($sweep) {
            $stats = $this->sweep();

            return $this->ok($back, __('ledger_map.sync_done', ['summary' => $this->summaryText($stats)]));
        }
        $done = $this->run($items);

        return $done['failed'] ? $this->err($back, $this->message($done)) : $this->ok($back, $this->message($done));
    }

    /** The opening entry if it is still missing, then the catch-up (what the scheduler runs). */
    private function sweep(): array
    {
        $sync = new LedgerSync();
        $stats = [];
        $opening = $sync->openingFromHistory(auth()->id());
        if ($opening) {
            $stats['opening'] = 1;
        }

        return $stats + $sync->sweep();
    }

    /** @return array result => [[type, id, reason]] */
    private function run(array $items): array
    {
        $sync = new LedgerSync();
        $out = ['posted' => [], 'reposted' => [], 'reversed' => [], 'same' => [], 'none' => [], 'history' => [], 'failed' => []];
        foreach ($items as [$type, $id]) {
            $r = $sync->record($type, $id);
            if ($r === 'failed') {
                $why = TransactionMapping::where('transaction_type', $type)->where('transaction_id', $id)->value('error');
                $out['failed'][] = [$type, $id, $why];
            } else {
                $out[$r][] = [$type, $id, null];
            }
        }

        return $out;
    }

    private function message(array $done): string
    {
        $parts = [];
        foreach (['posted', 'reposted', 'reversed', 'same', 'none', 'history', 'failed'] as $k) {
            if ($n = count($done[$k])) {
                $parts[] = trans_choice('ledger_map.res_'.$k, $n, ['count' => $n]);
            }
        }
        $msg = implode(', ', $parts).'.';
        foreach (array_slice($done['failed'], 0, 3) as [$type, $id, $why]) {
            $msg .= ' '.\App\Ledger\Services\PaymentBook::docLabel($type).' #'.$id.': '.$why;
        }

        return $msg;
    }

    private function summaryText(array $stats): string
    {
        $parts = [];
        foreach ($stats as $k => $n) {
            if (\Lang::has('ledger_map.res_'.$k)) {
                $parts[] = trans_choice('ledger_map.res_'.$k, $n, ['count' => $n]);
            }
        }

        return $parts ? implode(', ', $parts) : __('ledger_map.res_nothing');
    }

    /** The review: run the real sync on the selection, show the entries it writes, undo it all. */
    private function preview(array $items)
    {
        $p = Preview::blank();
        if (count($items) > self::MAX_ITEMS) {
            $p['ok'] = false;
            $p['problems'][] = __('ledger_map.err_too_many', ['max' => self::MAX_ITEMS]);

            return view('ledger.partials.preview', ['p' => $p]);
        }
        $done = null;
        $titles = [];
        Preview::dryRun($p, function () use ($items, &$done, &$titles) {
            $max = (int) DB::table('journal_entries')->max('id');
            $done = $this->run($items);
            $titles = $this->entryTitles($max);
        });
        $this->describe($p, $done, $titles, count($items));

        return view('ledger.partials.preview', ['p' => $p]);
    }

    private function previewSweep()
    {
        $p = Preview::blank();
        $stats = null;
        $titles = [];
        Preview::dryRun($p, function () use (&$stats, &$titles) {
            $max = (int) DB::table('journal_entries')->max('id');
            $stats = $this->sweep();
            $titles = $this->entryTitles($max);
        });
        if ($stats !== null) {
            $p['summary'][] = [__('ledger_map.sync_now'), $this->summaryText($stats)];
            if (($stats['failed'] ?? 0) > 0) {
                $p['warnings'][] = trans_choice('ledger_map.warn_would_fail', $stats['failed'], ['count' => $stats['failed']]);
            }
            if (empty($p['entries'])) {
                $p['notes'][] = __('ledger_map.note_nothing_to_post');
            }
            $p['notes'][] = __('ledger_map.note_sweep');
        }
        $this->titleAndTrim($p, $titles);

        return view('ledger.partials.preview', ['p' => $p]);
    }

    private function entryTitles(int $after): array
    {
        return JournalEntry::where('id', '>', $after)->orderBy('id')->get()->map(function ($e) {
            if ($e->reversed_entry_id) {
                $orig = JournalEntry::withTrashed()->find($e->reversed_entry_id);

                return __('ledger_map.title_reversal', ['no' => $orig->entry_number ?? '#'.$e->reversed_entry_id]);
            }

            return __('ledger_map.title_new_entry');
        })->all();
    }

    private function describe(array &$p, ?array $done, array $titles, int $count): void
    {
        if ($done !== null) {
            $p['summary'][] = [__('ledger_map.documents'), (string) $count];
            foreach (['posted', 'reposted', 'reversed', 'same', 'none', 'history', 'failed'] as $k) {
                if ($n = count($done[$k])) {
                    $p['summary'][] = [__('ledger_map.sum_'.$k), (string) $n];
                }
            }
            foreach (array_slice($done['failed'], 0, 20) as [$type, $id, $why]) {
                $p['warnings'][] = \App\Ledger\Services\PaymentBook::docLabel($type).' #'.$id.': '.$why;
            }
            if (count($done['failed']) === $count) {
                $p['ok'] = false;
                $p['problems'][] = __('ledger_map.err_all_fail');
            }
            if (count($done['same']) + count($done['none']) + count($done['history']) === $count) {
                $p['notes'][] = __('ledger_map.note_nothing_changes');
            }
            if ($done['reposted']) {
                $p['notes'][] = __('ledger_map.note_repost');
            }
            if ($done['history']) {
                $p['notes'][] = __('ledger_map.note_history');
            }
        }
        $this->titleAndTrim($p, $titles);
    }

    /** Title each entry (new / reversal of ...) and keep the dialog readable on a large sync. */
    private function titleAndTrim(array &$p, array $titles): void
    {
        foreach ($p['entries'] as $i => &$e) {
            if (isset($titles[$i])) {
                $e['title'] = $titles[$i];
            }
        }
        unset($e);
        $n = count($p['entries']);
        if ($n > 30) {
            $p['entries'] = array_slice($p['entries'], 0, 30);
            $p['notes'][] = __('ledger_map.note_more_entries', ['count' => $n - 30]);
        }
    }

    // ------------------------------------------------------------------ helpers

    private function postableAccounts()
    {
        return ChartOfAccount::postable()->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_name_fr']);
    }

    /** E-Ride: the categories of the Income screens (paxhitestbed's Income & Expense). */
    private function incomeCategories()
    {
        return DB::table('income_categories')->where('business_id', Ledger::businessId())->whereNull('deleted_at')
            ->orderBy('name')->get(['id', 'name', 'parent_id']);
    }

    private function expenseCategories()
    {
        // E-Ride: the categories of the Expenses screen (paxhitestbed's Accounts area, stage 2).
        if (! \Illuminate\Support\Facades\Schema::hasTable('expense_categories')) {
            return collect();
        }

        return DB::table('expense_categories')->where('business_id', Ledger::businessId())->whereNull('deleted_at')
            ->orderBy('name')->get(['id', 'name', 'parent_id']);
    }
}
