<?php

namespace Modules\BusManagement\Service\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAccAccount;
use Modules\BusManagement\Entities\BusAccDocument;
use Modules\BusManagement\Entities\BusAccEmployee;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAccPayslip;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompany;
use Modules\BusManagement\Lib\CameroonPayroll;

/**
 * Staff pay, what is owed to CNPS and the State, VAT and budgets, for a set of books (branch or head
 * office). Same rules as the rest: every posting goes through Ledger::post, by a document, previewed.
 *
 * A monthly payroll posts:
 *   Dr 6611 gross pay, Dr 6641 employer CNPS, Dr 6413 employer CFC and FNE
 *   Cr 4221 net pay owed to the staff, Cr 4311 CNPS (both shares), Cr 4471 State (IRPP, CAC, CFC, CRTV, TDL, FNE)
 * Paying the staff: Dr 4221 / Cr money. Paying CNPS, the State or VAT: Dr 4311 / 4471 / 4441 / Cr money.
 */
class PayService extends AccountingService
{
    // ------------------------------------------------------------------ staff

    public function saveEmployee(BusAgency|BusCompany $agency, array $data, ?BusAccEmployee $employee = null): BusAccEmployee
    {
        $this->needBooks($agency);
        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'job' => ['required', Rule::in(BusAccEmployee::JOBS)],
            'base_pay' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'cnps_number' => ['nullable', 'string', 'max:40'],
            'niu' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:20'],
            'hired_on' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
        ], [], ['base_pay' => translate('monthly pay')])->validate();
        $fields = [
            'name' => trim($data['name']), 'job' => $data['job'], 'base_pay' => round((float)$data['base_pay']), 'cnps_number' => $data['cnps_number'] ?? null,
            'niu' => $data['niu'] ?? null, 'phone' => $data['phone'] ?? null, 'hired_on' => $data['hired_on'] ?? null,
            'active' => array_key_exists('active', $data) ? (bool)$data['active'] : true,
        ];
        if ($employee) {
            $this->own($agency, $employee);
            $employee->update($fields);
            return $employee;
        }
        return BusAccEmployee::create($fields + ['agency_id' => (string)$agency->id]);
    }

    /** The employer rates of these books (Settings), over the defaults. */
    public function employerRates(BusAgency|BusCompany $agency): array
    {
        return array_merge(CameroonPayroll::EMPLOYER_DEFAULTS, (array)($this->ledger->books($agency)?->setting('pay_rates', []) ?? []));
    }

    /**
     * A month's payroll: every active person (or those given), their monthly pay plus any extra
     * (bonus, overtime), each withholding worked out by the rules (never typed in), one payslip
     * each, and one entry for the whole.
     */
    public function payroll(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $data = $this->validate($agency, $data, [
            'month' => ['required', 'date_format:Y-m'],
            'date' => ['required', 'date'],
            'staff' => ['required', 'array'],
            'staff.*.include' => ['nullable', 'boolean'],
            'staff.*.extra' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['month' => translate('month')]);
        $month = Carbon::createFromFormat('Y-m-d', $data['month'] . '-01')->startOfMonth();
        if ($month->gt(today()->startOfMonth())) {
            throw ValidationException::withMessages(['month' => translate('A payroll is for this month or a month already gone.')]);
        }
        if (Carbon::parse($data['date'])->lt($month)) {
            throw ValidationException::withMessages(['date' => translate('The payroll is dated in its month or after.')]);
        }
        $existing = BusAccDocument::where('agency_id', $agency->id)->where('type', BusAccDocument::PAYROLL)->where('status', 'posted')
            ->where('lines->month', $month->format('Y-m'))->first();
        if ($existing) {
            throw ValidationException::withMessages(['month' => translate(key: 'The payroll of {month} is already posted ({number}). Void it first to redo it.', replace: ['month' => $month->translatedFormat('F Y'), 'number' => $existing->number])]);
        }
        $people = BusAccEmployee::where('agency_id', $agency->id)->whereIn('id', array_keys($data['staff']))->get()->keyBy(fn($e) => (string)$e->id);
        if (count($people) !== count($data['staff'])) {
            throw ValidationException::withMessages(['staff' => translate('Someone in the list is not on the staff of these books.')]);
        }
        $rates = $this->employerRates($agency);
        $slips = [];
        foreach ($data['staff'] as $id => $row) {
            if (empty($row['include'])) {
                continue;
            }
            $e = $people[(string)$id];
            $extra = round((float)($row['extra'] ?? 0));
            $c = CameroonPayroll::compute($e->base_pay + $extra, $rates);
            if ($c['gross'] <= 0) {
                continue;
            }
            $slips[] = ['employee' => $e, 'base' => $e->base_pay, 'extra' => $extra] + $c;
        }
        if (!$slips) {
            throw ValidationException::withMessages(['staff' => translate('Tick at least one person with a pay above zero.')]);
        }
        $sum = fn(string $k) => round(array_sum(array_column($slips, $k)), 2);
        $gross = $sum('gross');
        $net = $sum('net');
        $cnps = $sum('cnps') + $sum('employer_cnps');
        $state = $sum('irpp') + $sum('cac') + $sum('cfc') + $sum('crtv') + $sum('tdl') + $sum('employer_taxes');

        return DB::transaction(function () use ($agency, $data, $userId, $month, $slips, $sum, $gross, $net, $cnps, $state) {
            $doc = $this->document($agency, BusAccDocument::PAYROLL, $data, $userId, [
                'amount' => $gross, 'owed' => $net,
                'lines' => ['month' => $month->format('Y-m'), 'people' => count($slips), 'gross' => $gross, 'net' => $net, 'cnps' => $cnps, 'state' => $state,
                    'employer_cnps' => $sum('employer_cnps'), 'employer_taxes' => $sum('employer_taxes')],
            ]);
            foreach ($slips as $s) {
                BusAccPayslip::create([
                    'agency_id' => (string)$agency->id, 'document_id' => (string)$doc->id, 'employee_id' => (string)$s['employee']->id, 'month' => $month->toDateString(),
                    'base' => $s['base'], 'extra' => $s['extra'], 'gross' => $s['gross'], 'cnps' => $s['cnps'], 'irpp' => $s['irpp'], 'cac' => $s['cac'],
                    'cfc' => $s['cfc'], 'crtv' => $s['crtv'], 'tdl' => $s['tdl'], 'net' => $s['net'], 'employer_cnps' => $s['employer_cnps'],
                    'employer_taxes' => $s['employer_taxes'], 'detail' => ['employer' => $s['employer'], 'name' => $s['employee']->name, 'job' => $s['employee']->job],
                ]);
            }
            $a = fn(string $role) => $this->ledger->account($agency, $role);
            $this->postDocument($agency, $doc, 'general', translate(key: 'Payroll of {month}', replace: ['month' => $month->translatedFormat('F Y')]) . ' · ' . translate(key: '{n} people', replace: ['n' => count($slips)]), [
                [$a('salaries'), $gross, 0],
                [$a('employer_cnps'), $sum('employer_cnps'), 0],
                [$a('payroll_taxes'), $sum('employer_taxes'), 0],
                [$a('pay_owed'), 0, $net],
                [$a('cnps'), 0, $cnps],
                [$a('state_tax'), 0, $state],
            ], $userId);
            return $doc->fresh();
        });
    }

    /** The staff are paid what a payroll owes them (the net), from one money account. */
    public function payStaff(BusAgency|BusCompany $agency, BusAccDocument $payroll, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $this->own($agency, $payroll);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'money_account_id' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], ['money_account_id' => translate('paid from')]);
        return DB::transaction(function () use ($agency, $payroll, $data, $userId) {
            $payroll = BusAccDocument::whereKey($payroll->id)->lockForUpdate()->first();
            if ($payroll->type !== BusAccDocument::PAYROLL || $payroll->status !== 'posted' || $payroll->owed <= 0.004) {
                throw ValidationException::withMessages(['document' => translate('Nothing is owed to the staff on this payroll.')]);
            }
            if (Carbon::parse($data['date'])->lt($payroll->date)) {
                throw ValidationException::withMessages(['date' => translate('The staff cannot be paid before the payroll date.')]);
            }
            $money = $this->ownAccount($agency, $data['money_account_id'], 'money');
            if ($money->role === 'counter_cash') {
                throw ValidationException::withMessages(['money_account_id' => translate('Pay the staff from the safe, the bank or Mobile Money, not from an agent cash.')]);
            }
            $amount = $payroll->owed;
            $doc = $this->document($agency, BusAccDocument::STAFF_PAY, $data, $userId, [
                'amount' => $amount, 'parent_id' => (string)$payroll->id, 'money_account_id' => (string)$money->id,
            ]);
            $this->postDocument($agency, $doc, 'cash', translate('Staff paid') . ' · ' . $payroll->number,
                [[$this->ledger->account($agency, 'pay_owed'), $amount, 0], [$money, 0, $amount]], $userId);
            $payroll->update(['owed' => 0]);
            return $doc->fresh();
        });
    }

    /** What is owed to CNPS, to the State (taxes withheld), and in VAT. */
    public function owedToAuthorities(BusAgency|BusCompany $agency): array
    {
        $bal = fn(string $role) => ($acc = BusAccAccount::where('agency_id', $agency->id)->where('role', $role)->first()) ? $this->ledger->balance($acc) : 0.0;
        return ['cnps' => $bal('cnps'), 'state' => $bal('state_tax'), 'vat' => $bal('vat_due')];
    }

    /** Paying CNPS, the State (taxes withheld) or VAT, from a money account, with the receipt number. */
    public function remit(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'to' => ['required', Rule::in(['cnps', 'state', 'vat'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'money_account_id' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], ['money_account_id' => translate('paid from')]);
        $role = ['cnps' => 'cnps', 'state' => 'state_tax', 'vat' => 'vat_due'][$data['to']];
        $owed = round($this->ledger->balance($this->ledger->account($agency, $role)), 2);
        $amount = round((float)$data['amount'], 2);
        if ($amount > $owed + 0.004) {
            throw ValidationException::withMessages(['amount' => translate(key: 'Only {amount} is owed there.', replace: ['amount' => $this->money($owed)])]);
        }
        $money = $this->ownAccount($agency, $data['money_account_id'], 'money');
        if ($money->role === 'counter_cash') {
            throw ValidationException::withMessages(['money_account_id' => translate('Pay from the safe, the bank or Mobile Money, not from an agent cash.')]);
        }
        $label = ['cnps' => translate('CNPS (social security)'), 'state' => translate('Taxes withheld for the State'), 'vat' => translate('VAT')][$data['to']];
        return DB::transaction(function () use ($agency, $data, $userId, $role, $amount, $money, $label) {
            $doc = $this->document($agency, BusAccDocument::REMITTANCE, $data, $userId, [
                'amount' => $amount, 'money_account_id' => (string)$money->id, 'account_id' => (string)$this->ledger->account($agency, $role)->id,
                'lines' => ['to' => $data['to']],
            ]);
            $this->postDocument($agency, $doc, 'cash', translate('Paid') . ' · ' . $label,
                [[$this->ledger->account($agency, $role), $amount, 0], [$money, 0, $amount]], $userId);
            return $doc->fresh();
        });
    }

    // ------------------------------------------------------------------ VAT

    public function vatOn(BusAgency|BusCompany|string $agency): bool
    {
        return (bool)$this->ledger->books($agency)?->setting('vat_registered', false);
    }

    public function vatRate(BusAgency|BusCompany|string $agency): float
    {
        return (float)($this->ledger->books($agency)?->setting('vat_rate', 19.25) ?? 19.25);
    }

    /**
     * A month's VAT return: the VAT collected on sales less the VAT on purchases, moved to "VAT to
     * pay" (or a credit carried when purchases carried more).
     */
    public function vatReturn(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        if (!$this->vatOn($agency)) {
            throw ValidationException::withMessages(['month' => translate('These books are not registered for VAT (Settings).')]);
        }
        $data = $this->validate($agency, $data, ['month' => ['required', 'date_format:Y-m'], 'date' => ['required', 'date']], ['month' => translate('month')]);
        $month = Carbon::createFromFormat('Y-m-d', $data['month'] . '-01')->startOfMonth();
        if ($month->copy()->endOfMonth()->gte(today())) {
            throw ValidationException::withMessages(['month' => translate('A VAT return is made once the month is over.')]);
        }
        if (BusAccDocument::where('agency_id', $agency->id)->where('type', BusAccDocument::VAT_RETURN)->where('status', 'posted')->where('lines->month', $month->format('Y-m'))->exists()) {
            throw ValidationException::withMessages(['month' => translate('The VAT return of this month is already posted.')]);
        }
        $from = $month->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();
        $returns = BusAccDocument::where('agency_id', $agency->id)->where('type', BusAccDocument::VAT_RETURN)->pluck('entry_id')->filter();
        $move = function (string $role) use ($agency, $from, $to, $returns) {
            $acc = $this->ledger->account($agency, $role);
            $r = BusAccLine::where('agency_id', $agency->id)->where('account_id', $acc->id)->whereBetween('date', [$from, $to])
                ->whereNotIn('entry_id', $returns)->selectRaw('coalesce(sum(debit),0) d, coalesce(sum(credit),0) c')->first();
            return [$acc, round((float)$r->d - (float)$r->c, 2)];
        };
        [$out, $outNet] = $move('vat_out');
        [$in, $inNet] = $move('vat_in');
        $collected = -$outNet;
        $deductible = $inNet;
        $due = round($collected - $deductible, 2);
        if (abs($collected) < 0.005 && abs($deductible) < 0.005) {
            throw ValidationException::withMessages(['month' => translate('No VAT was collected or paid in this month.')]);
        }
        return DB::transaction(function () use ($agency, $data, $userId, $month, $out, $in, $collected, $deductible, $due) {
            $doc = $this->document($agency, BusAccDocument::VAT_RETURN, $data, $userId, [
                'amount' => abs($due), 'expected' => $collected, 'counted' => $deductible,
                'lines' => ['month' => $month->format('Y-m'), 'collected' => $collected, 'deductible' => $deductible, 'due' => $due],
            ]);
            $lines = [];
            if (abs($collected) >= 0.005) {
                $lines[] = $collected > 0 ? [$out, $collected, 0] : [$out, 0, -$collected];
            }
            if (abs($deductible) >= 0.005) {
                $lines[] = $deductible > 0 ? [$in, 0, $deductible] : [$in, -$deductible, 0];
            }
            $vatDue = $this->ledger->account($agency, 'vat_due');
            $lines[] = $due >= 0 ? [$vatDue, 0, $due] : [$vatDue, -$due, 0];
            $this->postDocument($agency, $doc, 'general', translate(key: 'VAT return of {month}', replace: ['month' => $month->translatedFormat('F Y')]), $lines, $userId);
            return $doc->fresh();
        });
    }

    // ------------------------------------------------------------------ budgets

    /** Accounts a budget is set for: income and spending (classes 6 and 7, and 8 for buses sold). */
    public function budgetAccounts(BusAgency|BusCompany $agency)
    {
        return BusAccAccount::where('agency_id', $agency->id)->whereIn('type', ['revenue', 'expense'])->where('active', true)->orderBy('code')->get();
    }

    /** Saves a year's budget: amounts[account_id][1..12]. Not a posting: a plan. */
    public function saveBudget(BusAgency|BusCompany $agency, int $year, array $amounts): int
    {
        $this->needBooks($agency);
        if ($year < 2020 || $year > today()->year + 2) {
            throw ValidationException::withMessages(['year' => translate('Choose a year from the list.')]);
        }
        $ids = $this->budgetAccounts($agency)->pluck('id')->map(fn($i) => (string)$i)->flip();
        $n = 0;
        DB::transaction(function () use ($agency, $year, $amounts, $ids, &$n) {
            foreach ($amounts as $accountId => $months) {
                if (!isset($ids[(string)$accountId]) || !is_array($months)) {
                    continue;
                }
                foreach ($months as $m => $v) {
                    $m = (int)$m;
                    if ($m < 1 || $m > 12) {
                        continue;
                    }
                    $v = is_numeric($v) ? round(max(0, (float)$v)) : 0;
                    DB::table('bus_acc_budgets')->updateOrInsert(['agency_id' => (string)$agency->id, 'year' => $year, 'account_id' => (string)$accountId, 'month' => $m],
                        ['amount' => $v, 'updated_at' => now(), 'created_at' => now()]);
                    $n++;
                }
            }
        });
        return $n;
    }

    /** amounts[account_id][month] of a year's budget. */
    public function budget(BusAgency|BusCompany $agency, int $year): array
    {
        $out = [];
        foreach (DB::table('bus_acc_budgets')->where('agency_id', $agency->id)->where('year', $year)->get() as $r) {
            $out[(string)$r->account_id][(int)$r->month] = (float)$r->amount;
        }
        return $out;
    }
}
