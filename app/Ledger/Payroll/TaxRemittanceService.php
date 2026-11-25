<?php

namespace App\Ledger\Payroll;

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\PayrollTaxLine;
use App\Ledger\Models\TaxGroup;
use App\Ledger\Models\TaxRemittance;
use App\Ledger\Models\TaxSetting;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PaymentBook;
use App\Ledger\Services\PostingRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * paxhitestbed TaxRemittanceService: what the books hold on somebody else's behalf (the CNPS, the
 * State), per authority and salary month, and paying it over with the receipt number.
 *
 * What is owed comes from the LEDGER (the payroll entries and their reversals, netted), what it is
 * made of from the itemised tax lines. E-Ride: an entry's reference is its mapping row, so the
 * payroll entries are found through transaction_mappings (kind 'payroll'); a remittance is paid
 * from a payment account, posted by the sync (PostingRules::forTaxRemittance), and voiding it
 * reverses the entry (never a delete).
 */
class TaxRemittanceService
{
    /** The liability accounts payroll withholdings can be owed on. */
    public function liabilityAccountIds(): array
    {
        $ids = PayrollTaxLine::whereNotNull('liability_account_id')->distinct()->pluck('liability_account_id')
            ->merge(TaxSetting::whereNotNull('liability_account_id')->distinct()->pluck('liability_account_id'))
            ->merge(TaxGroup::whereNotNull('liability_account_id')->distinct()->pluck('liability_account_id'))
            ->merge(array_filter([PayrollAccounts::id('payroll_tax'), PayrollAccounts::id('payroll_social')]));

        return $ids->map(fn ($i) => (int) $i)->unique()->values()->all();
    }

    public function outstanding(?string $upToMonth = null): array
    {
        $bid = Ledger::businessId();
        $withheld = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('transaction_mappings as m', 'm.id', '=', 'e.reference_id')
            ->join('payrolls as p', 'p.id', '=', 'm.transaction_id')
            ->where('e.business_id', $bid)->where('m.business_id', $bid)->where('p.business_id', $bid)
            ->where('m.transaction_type', PostingRules::PAYROLL)
            // the payroll's entries and their reversals, netted: unpaying takes the liability back out
            ->whereIn('e.reference_type', [PostingRules::PAYROLL, 'transaction_mapping'])
            ->where('e.is_posted', 1)->whereNull('e.deleted_at')
            ->whereIn('l.account_id', $this->liabilityAccountIds() ?: [0])
            ->when($upToMonth, fn ($q) => $q->whereDate('p.salary_month', '<=', $upToMonth))
            ->groupBy('l.account_id', 'p.salary_month')
            ->selectRaw('l.account_id as account_id, p.salary_month as month, COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) as due')
            ->havingRaw('COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) <> 0')
            ->get();

        $composition = PayrollTaxLine::query()
            ->join('payrolls as p', 'p.id', '=', 'payroll_tax_lines.payroll_id')
            ->where(fn ($w) => $w->where('p.status', 1)->orWhereNotNull('p.approved_at')) // accrued: approved or paid
           ->whereNotNull('payroll_tax_lines.liability_account_id')
            ->groupBy('payroll_tax_lines.liability_account_id', 'payroll_tax_lines.salary_month')
            ->selectRaw('payroll_tax_lines.liability_account_id as account_id, payroll_tax_lines.salary_month as month,
                         SUM(payroll_tax_lines.employee_amount) as employee, SUM(payroll_tax_lines.employer_amount) as employer,
                         COUNT(DISTINCT payroll_tax_lines.payroll_id) as staff_count')
            ->get()->keyBy(fn ($r) => $r->account_id.'|'.substr((string) $r->month, 0, 10));

        $paid = TaxRemittance::live()->groupBy('liability_account_id', 'salary_month')
            ->selectRaw('liability_account_id as account_id, salary_month as month, SUM(amount) as paid')
            ->get()->keyBy(fn ($r) => $r->account_id.'|'.substr((string) $r->month, 0, 10));

        // a month paid over but with nothing withheld any more (a payroll unpaid since) still shows
        $keys = $withheld->map(fn ($r) => $r->account_id.'|'.substr((string) $r->month, 0, 10))->all();
        foreach ($paid as $key => $p) {
            if (! in_array($key, $keys, true)) {
                $withheld->push((object) ['account_id' => $p->account_id, 'month' => $p->month, 'due' => 0]);
            }
        }

        $accounts = ChartOfAccount::whereIn('id', $withheld->pluck('account_id')->unique()->all())->get()->keyBy('id');
        $rows = [];
        foreach ($withheld as $row) {
            $month = substr((string) $row->month, 0, 10);
            $key = $row->account_id.'|'.$month;
            $due = round((float) $row->due, 2);
            $settled = round((float) ($paid[$key]->paid ?? 0), 2);
            $account = $accounts->get($row->account_id);
            $parts = $composition->get($key);
            $rows[] = [
                'account_id' => (int) $row->account_id,
                'account_code' => $account->account_code ?? '?',
                'account_name' => $account ? \App\Ledger\Services\Preview::accountName($account) : '?',
                'month' => $month,
                'month_label' => Carbon::parse($month)->translatedFormat('F Y'),
                'employee' => round((float) ($parts->employee ?? 0), 2),
                'employer' => round((float) ($parts->employer ?? 0), 2),
                'itemised' => $parts !== null,
                'due' => $due,
                'paid' => $settled,
                'outstanding' => round($due - $settled, 2),
                'staff_count' => (int) ($parts->staff_count ?? 0),
                'status' => $this->statusFor($due, $settled),
            ];
        }
        usort($rows, fn ($a, $b) => [$a['month'], $a['account_code']] <=> [$b['month'], $b['account_code']]);

        return $rows;
    }

    private function statusFor(float $due, float $paid): string
    {
        if ($paid <= 0) {
            return 'undeclared';
        }
        if (abs($due - $paid) < 0.01) {
            return 'settled';
        }

        return $paid > $due ? 'overpaid' : 'part_paid';
    }

    /** The taxes behind one authority and month, for the declaration form. */
    public function breakdownFor(int $accountId, string $month): array
    {
        return PayrollTaxLine::query()
            ->join('payrolls as p', 'p.id', '=', 'payroll_tax_lines.payroll_id')
            ->where(fn ($w) => $w->where('p.status', 1)->orWhereNotNull('p.approved_at')) // accrued: approved or paid
           
            ->where('payroll_tax_lines.liability_account_id', $accountId)
            ->whereDate('payroll_tax_lines.salary_month', $month)
            ->groupBy('payroll_tax_lines.label')
            ->selectRaw('payroll_tax_lines.label, SUM(payroll_tax_lines.employee_amount) as employee, SUM(payroll_tax_lines.employer_amount) as employer,
                         COUNT(DISTINCT payroll_tax_lines.staff_id) as staff_count')
            ->orderByDesc(DB::raw('SUM(payroll_tax_lines.employee_amount) + SUM(payroll_tax_lines.employer_amount)'))
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'employee' => round((float) $r->employee, 2), 'employer' => round((float) $r->employer, 2),
                'total' => round((float) $r->employee + (float) $r->employer, 2), 'staff_count' => (int) $r->staff_count])
            ->all();
    }

    /** Does what the months say is owed agree with what each liability account holds? */
    public function reconciliation(?array $rows = null): array
    {
        $expected = [];
        foreach ($rows ?? $this->outstanding() as $row) {
            $expected[$row['account_id']] = ($expected[$row['account_id']] ?? 0) + $row['outstanding'];
        }
        $out = [];
        foreach ($expected as $accountId => $amount) {
            $account = ChartOfAccount::find($accountId);
            if (! $account) {
                continue;
            }
            $ledger = $this->ledgerBalance($accountId);
            $difference = round($ledger - $amount, 2);
            $out[] = ['account_id' => (int) $accountId, 'account_code' => $account->account_code, 'account_name' => \App\Ledger\Services\Preview::accountName($account),
                'per_month' => round($amount, 2), 'ledger' => $ledger, 'difference' => $difference, 'agrees' => abs($difference) < 0.51];
        }

        return $out;
    }

    /** What a liability account carries, from posted entries only (credit balance). */
    public function ledgerBalance(int $accountId): float
    {
        $row = DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.business_id', Ledger::businessId())->where('l.account_id', $accountId)->where('e.is_posted', 1)->whereNull('e.deleted_at')
            ->selectRaw('COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) as balance')->first();

        return round((float) $row->balance, 2);
    }

    /** Record a payment to an authority and post it (Dr the liability / Cr the payment account). */
    public function record(array $input, ?string $userId): TaxRemittance
    {
        $month = Carbon::parse($input['salary_month'])->startOfMonth()->toDateString();
        $accountId = (int) $input['liability_account_id'];
        $amount = round((float) $input['amount'], 2);
        if ($amount <= 0) {
            throw new \RuntimeException(__('ledger_payroll.err_remit_zero'));
        }
        if (! in_array($accountId, $this->liabilityAccountIds(), true)) {
            throw new \RuntimeException(__('ledger_payroll.err_remit_account'));
        }
        $from = PaymentAccount::find($input['payment_account_id'] ?? 0);
        if (! $from || ! $from->chart_of_account_id) {
            throw new \RuntimeException(__('ledger_payroll.err_remit_source'));
        }
        if (PaymentBook::available($from->id) < $amount - 0.005) {
            throw new \RuntimeException(__('ledger_payroll.err_insufficient', ['balance' => Ledger::money(PaymentBook::available($from->id))]));
        }
        $already = (float) TaxRemittance::live()->where('liability_account_id', $accountId)->whereDate('salary_month', $month)->sum('amount');
        if ($already > 0 && empty($input['allow_additional'])) {
            throw new \RuntimeException(__('ledger_payroll.err_remit_already'));
        }
        $due = 0.0;
        foreach ($this->outstanding() as $row) {
            if ($row['account_id'] === $accountId && $row['month'] === $month) {
                $due = $row['due'];
                break;
            }
        }
        if ($amount > round($due - $already, 2) + 0.01 && empty($input['allow_overpayment'])) {
            throw new \RuntimeException(__('ledger_payroll.err_remit_more'));
        }

        return DB::transaction(function () use ($input, $userId, $month, $accountId, $amount, $from) {
            $remittance = TaxRemittance::create([
                'liability_account_id' => $accountId,
                'salary_month' => $month,
                'amount' => $amount,
                'payment_date' => $input['payment_date'],
                'payment_account_id' => $from->id,
                'source_account_id' => $from->chart_of_account_id,
                'reference' => $input['reference'] ?? null,
                'note' => $input['note'] ?? null,
                'created_by' => $userId,
            ]);
            $remittance->load('liabilityAccount');
            PaymentBook::link('tax_remittance', $remittance->id, $from->id, 'debit', $amount, Carbon::parse($input['payment_date'])->toDateString(),
                __('ledger_payroll.tax_remittance').' - '.($remittance->liabilityAccount ? \App\Ledger\Services\Preview::accountName($remittance->liabilityAccount) : ''),
                Carbon::parse($month)->translatedFormat('F Y'), null, $remittance->reference, $userId);
            $result = (new LedgerSync())->record(PostingRules::TAX_REMITTANCE, $remittance->id);
            if (! in_array($result, ['posted', 'same'], true)) {
                throw new \RuntimeException($this->why($remittance->id) ?: __('ledger_payroll.err_not_posted'));
            }

            return $remittance;
        });
    }

    /** Void with a reversing entry; the row stays (the audit trail). */
    public function void(TaxRemittance $remittance, ?string $userId, ?string $reason = null): TaxRemittance
    {
        if ($remittance->isVoided()) {
            throw new \RuntimeException(__('ledger_payroll.err_remit_voided'));
        }

        return DB::transaction(function () use ($remittance, $userId, $reason) {
            $remittance->fill(['voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $reason, 'updated_by' => $userId])->save();
            PaymentBook::remove('tax_remittance', $remittance->id);
            $result = (new LedgerSync())->record(PostingRules::TAX_REMITTANCE, $remittance->id);
            if (! in_array($result, ['reversed', 'none'], true)) {
                throw new \RuntimeException($this->why($remittance->id) ?: __('ledger_payroll.err_not_posted'));
            }

            return $remittance;
        });
    }

    private function why(int $id): ?string
    {
        $map = TransactionMapping::where('transaction_type', PostingRules::TAX_REMITTANCE)->where('transaction_id', (string) $id)->first();

        return $map && $map->status === 'failed' ? $map->error : null;
    }
}
