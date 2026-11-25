<?php

namespace App\Ledger\Payroll;

use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\Payroll;
use App\Ledger\Models\TaxGroup;
use App\Ledger\Models\TaxSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * paxhitestbed's TaxSettingSeeder for a set of books: the verified Cameroon configuration (five
 * income-tax bands from the DGI barème, ten local development bands, the housing fund with both
 * shares, eleven CRTV bands, the industrial-accident group switched off, the CNPS with its ceiling
 * as two rows, the employment fund, and the council surcharge as 10% of the income tax).
 *
 * Its two rules are kept: it never deletes, and it never touches books that already have their
 * own rates or payroll history. It runs once per set of books, the first time a payroll or tax
 * screen opens (a flag in the accounting settings), so rates a manager removed are not put back.
 *
 * Liability accounts: the CNPS and the employment fund are owed on payroll_social (431), every
 * other tax on payroll_tax (447 taxes withheld at source). Effective from the 1st of January of the
 * year the books install them, so the year's payrolls can all be generated.
 */
class TaxDefaults
{
    public const FLAG = 'payroll_tax_defaults_installed';

    private ?int $state = null;

    private ?int $social = null;

    public static function ensure(): void
    {
        if (AccountingSetting::getValue(self::FLAG)) {
            return;
        }
        if (! TaxGroup::exists() && ! TaxSetting::exists() && ! Payroll::where('status', 1)->exists()) {
            (new self())->install();
        }
        AccountingSetting::setValue(self::FLAG, now()->toDateTimeString());
    }

    public function install(?string $effectiveFrom = null): void
    {
        $from = $effectiveFrom ?: Carbon::today()->startOfYear()->toDateString();
        $this->state = PayrollAccounts::id('payroll_tax');
        $this->social = PayrollAccounts::id('payroll_social');

        DB::transaction(function () use ($from) {
            $pit = $this->group('PIT', true, 1, 1);
            foreach (CameroonTaxTable::INCOME_TAX as $band) {
                $this->row($pit, [
                    'title' => sprintf('IRPP band %d: %s%% above %s', $band['order'], number_format($band['percent'], 2), number_format($band['threshold'])),
                    'bracket_order' => $band['order'], 'tax_type' => 1, 'paid_by' => 'employee',
                    'min_amount' => $band['min'], 'max_amount' => $band['max'],
                    'percentange' => $band['percent'], 'max_no_taxable_amount' => $band['threshold'],
                    'liability_account_id' => $this->state,
                ], $from);
            }

            $ldt = $this->group('LDT', false, 1, 2);
            foreach (CameroonTaxTable::LOCAL_DEVELOPMENT as $band) {
                $this->row($ldt, ['title' => sprintf('Local Development Tax, band %d', $band['order']), 'bracket_order' => $band['order'],
                    'tax_type' => 2, 'paid_by' => 'employee', 'min_amount' => $band['min'], 'max_amount' => $band['max'],
                    'fixed_amount' => $band['amount'], 'liability_account_id' => $this->state], $from);
            }

            $hlf = $this->group('HLF', false, 1, 3);
            $this->row($hlf, ['title' => 'Housing Loan Fund', 'bracket_order' => 1, 'tax_type' => 1, 'paid_by' => 'both', 'is_shared' => 1,
                'min_amount' => CameroonTaxTable::FLOOR, 'max_amount' => CameroonTaxTable::OPEN_TOP,
                'percentange' => CameroonTaxTable::CFC_EMPLOYEE_RATE, 'employer_percentage' => CameroonTaxTable::CFC_EMPLOYER_RATE,
                'liability_account_id' => $this->state], $from);

            $crtv = $this->group('CRTV', false, 1, 4);
            foreach (CameroonTaxTable::AUDIO_VISUAL as $band) {
                $this->row($crtv, ['title' => sprintf('CRTV band %d', $band['order']), 'bracket_order' => $band['order'], 'tax_type' => 2,
                    'paid_by' => 'employee', 'min_amount' => $band['min'], 'max_amount' => $band['max'], 'fixed_amount' => $band['amount'],
                    'liability_account_id' => $this->state], $from);
            }

            // a real employer obligation whose rate depends on the sector: there for the manager to fill in
            $this->group('IA', false, 0, 5, $this->social);

            $this->row(null, ['title' => 'National Social Insurance Fund', 'bracket_order' => 1, 'tax_type' => 1, 'paid_by' => 'both', 'is_shared' => 1,
                'min_amount' => 0, 'max_amount' => CameroonTaxTable::CNPS_CAP_BASE - 1,
                'percentange' => CameroonTaxTable::CNPS_RATE, 'employer_percentage' => CameroonTaxTable::CNPS_RATE,
                'liability_account_id' => $this->social], $from);
            $this->row(null, ['title' => 'National Social Insurance Fund, at the ceiling', 'bracket_order' => 2, 'tax_type' => 2, 'paid_by' => 'both', 'is_shared' => 1,
                'min_amount' => CameroonTaxTable::CNPS_CAP_BASE, 'max_amount' => CameroonTaxTable::OPEN_TOP,
                'fixed_amount' => CameroonTaxTable::CNPS_CAP_AMOUNT, 'employer_fixed_amount' => CameroonTaxTable::CNPS_CAP_AMOUNT,
                'liability_account_id' => $this->social], $from);
            $this->row(null, ['title' => 'National Employment Fund', 'bracket_order' => 3, 'tax_type' => 1, 'paid_by' => 'employer',
                'min_amount' => 0, 'max_amount' => CameroonTaxTable::OPEN_TOP, 'percentange' => 0,
                'employer_percentage' => CameroonTaxTable::FNE_EMPLOYER_RATE, 'liability_account_id' => $this->social], $from);
            $this->row(null, ['title' => 'Additional Council Tax', 'bracket_order' => 4, 'is_dependent' => 1, 'depends_on_type' => 'tax_group',
                'depends_on_id' => $pit, 'tax_type' => 1, 'paid_by' => 'employee', 'min_amount' => 0, 'max_amount' => CameroonTaxTable::OPEN_TOP,
                'percentange' => CameroonTaxTable::CAC_RATE, 'liability_account_id' => $this->state], $from);
        });
    }

    private function group(string $code, bool $progressive, int $status, int $order, ?int $account = null): int
    {
        return (int) TaxGroup::create([
            'title' => CameroonTaxTable::GROUPS[$code], 'code' => $code, 'is_progressive' => $progressive, 'status' => $status,
            'display_order' => $order, 'liability_account_id' => $account ?? $this->state,
        ])->id;
    }

    private function row(?int $groupId, array $attributes, string $from): void
    {
        TaxSetting::create($attributes + [
            'tax_group_id' => $groupId, 'is_dependent' => 0, 'is_shared' => 0, 'percentange' => 0, 'employer_percentage' => 0,
            'fixed_amount' => 0, 'employer_fixed_amount' => 0, 'max_no_taxable_amount' => 0, 'status' => 1, 'effective_from' => $from,
        ]);
    }
}
