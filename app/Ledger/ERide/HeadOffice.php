<?php

namespace App\Ledger\ERide;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingSetting;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Services\DefaultAccount;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PostingRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompany;
use Modules\BusManagement\Entities\BusMoneyMove;
use Modules\BusManagement\Entities\BusStatement;

/**
 * A bus company's head office books (E-Ride): what the bus records put there.
 *
 * A branch keeps its own books; the head office keeps its own too, joined to
 * each branch by liaison accounts (OHADA class 18): the branch's 1811 "Head
 * office" and, at the head office, one account per branch (1821, 1822...).
 * They mirror each other, so the combined statements cancel them out.
 *
 *   ticket money collected on the company's account   (company money mode)
 *       branch:       Dr 1811 head office / Cr 477 (its own rule, PostingRules::forMove; Fapshi's share Dr 631 / Cr 1811)
 *       head office:  Dr 5523 Fapshi (net of Fapshi's share) / Cr 182x the branch
 *   E-Ride's statement paid by the head office         (company bill mode)
 *       branch:       Dr 401 E-Ride / Cr 1811 head office (PostingRules::forStatementPaid)
 *       head office:  Dr 182x the branch / Cr the money it was paid from
 *
 * Posted with LedgerSync's mechanism (mappings, fingerprint, reverse and repost).
 */
class HeadOffice
{
    const MOVE = 'ho_move';
    const STATEMENT_PAID = 'ho_statement_paid';
    const KINDS = [self::MOVE, self::STATEMENT_PAID];

    /** The company whose head office books are open, or null. */
    public static function company(): ?BusCompany
    {
        $row = DB::table('ledger_businesses')->where('id', Ledger::businessId())->first();

        return $row && $row->owner_type === Books::COMPANY ? BusCompany::find($row->owner_id) : null;
    }

    /** The head office books of a branch's company (when it keeps them), or 0. */
    public static function booksOfBranch(string $agencyId): int
    {
        $company = DB::table('bus_agencies')->where('id', $agencyId)->value('company_id');
        if (! $company) {
            return 0;
        }
        $bid = (int) DB::table('ledger_businesses')->where('owner_type', Books::COMPANY)->where('owner_id', $company)->value('id');

        return $bid && Ledger::isSetUp($bid) ? $bid : 0;
    }

    public static function record(LedgerSync $sync, string $kind, $id): string
    {
        $company = self::company();
        if (! $company) {
            return 'none';
        }
        if ($kind === self::MOVE) {
            $m = BusMoneyMove::find((int) $id);
            $ok = $m && $m->method === 'company' && in_array($m->kind, [BusMoneyMove::SALE, BusMoneyMove::EXTRA], true)
                && DB::table('bus_agencies')->where('id', $m->agency_id)->value('company_id') === (string) $company->id;
            $map = TransactionMapping::where('transaction_type', $kind)->where('transaction_id', (string) $id)->first();
            if (! $ok) {
                return $map ? $sync->applyFor($map, null, $kind, $id) : 'none';
            }
            if (! $map && self::history($m->at)) {
                return 'history';
            }
            $map = $map ?: new TransactionMapping(['transaction_type' => $kind, 'transaction_id' => (string) $id]);

            return $sync->applyFor($map, fn () => self::forMove($m), $kind, $id);
        }
        if ($kind === self::STATEMENT_PAID) {
            $s = BusStatement::find((string) $id);
            $ok = $s && $s->company_statement_id && $s->status === BusStatement::PAID && $s->paid_at
                && DB::table('bus_agencies')->where('id', $s->agency_id)->value('company_id') === (string) $company->id;
            $map = TransactionMapping::where('transaction_type', $kind)->where('transaction_id', (string) $id)->first();
            if (! $ok) {
                return $map ? $sync->applyFor($map, null, $kind, $id) : 'none';
            }
            if (! $map && self::history($s->paid_at)) {
                return 'history';
            }
            $map = $map ?: new TransactionMapping(['transaction_type' => $kind, 'transaction_id' => (string) $id]);

            return $sync->applyFor($map, fn () => self::forStatementPaid($s), $kind, $id);
        }

        return 'none';
    }

    /** Ticket money collected on the company's account for a branch. */
    public static function forMove(BusMoneyMove $m): ?array
    {
        $amount = round((float) $m->amount, 2);
        if ($amount <= 0) {
            return null;
        }
        $rules = new PostingRules();
        $fee = $m->kind === BusMoneyMove::SALE ? (float) ($rules->fee($m) ?? 0) : 0.0;
        $net = round($amount - $fee, 2);
        $branch = BusAgency::find($m->agency_id);
        $code = DB::table('bus_bookings')->where('id', $m->booking_id)->value('code');
        $desc = __('ledger.l_ho_collected', ['branch' => $branch?->displayName() ?? '']);
        $money = self::mapping('payment_method', 'fapshi');

        return [
            'date' => Carbon::parse($m->at)->toDateString(),
            'description' => $desc.($code ? ' · '.$code : ''),
            'reference' => $code,
            'journal_type' => 'cash',
            'lines' => [
                ['account_id' => $money, 'debit' => $net, 'credit' => 0, 'description' => $desc],
                ['account_id' => self::branchAccount($m->agency_id), 'debit' => 0, 'credit' => $net, 'description' => $desc],
            ],
        ];
    }

    /** E-Ride's statement of a branch, paid by the head office. */
    public static function forStatementPaid(BusStatement $s): ?array
    {
        $total = round((float) $s->total, 2);
        if ($total <= 0) {
            return null;
        }
        $branch = BusAgency::find($s->agency_id);
        $desc = __('ledger.l_ho_statement_paid', ['branch' => $branch?->displayName() ?? '', 'month' => Carbon::parse($s->month)->translatedFormat('F Y')]);
        $from = self::mapping('payment_method', (string) (AccountingSetting::getValue('eride_paid_from') ?: 'momo'));

        return [
            'date' => Carbon::parse($s->paid_at)->toDateString(),
            'description' => $desc,
            'reference' => $s->reference,
            'journal_type' => 'cash',
            'lines' => [
                ['account_id' => self::branchAccount($s->agency_id), 'debit' => $total, 'credit' => 0, 'description' => $desc],
                ['account_id' => $from, 'debit' => 0, 'credit' => $total, 'description' => $desc],
            ],
        ];
    }

    /**
     * The head office's liaison account of a branch (Mapping settings: "branch_liaison",
     * key = the branch), made on first use: 1821, 1822... under 18, named after the branch.
     */
    public static function branchAccount(string $agencyId): int
    {
        $id = DefaultAccount::id('branch_liaison', $agencyId);
        if ($id) {
            return (int) $id;
        }
        $parent = ChartOfAccount::where('account_code', '18')->value('id');
        $used = ChartOfAccount::withTrashed()->where('account_code', 'like', '182%')->pluck('account_code')->all();
        $n = 1;
        while (in_array('182'.$n, $used, true)) {
            $n++;
        }
        $name = BusAgency::find($agencyId)?->displayName() ?? $agencyId;
        $acc = ChartOfAccount::create([
            'account_code' => '182'.$n, 'account_name' => __('ledger.liaison_branch', ['branch' => $name], 'en'),
            'account_name_fr' => __('ledger.liaison_branch', ['branch' => $name], 'fr'), 'parent_id' => $parent,
            'class_number' => 1, 'account_type' => 'liability', 'account_category' => 'detail', 'cash_flow_category' => 'financing',
            'normal_balance' => 'credit', 'is_active' => true, 'is_system' => true,
        ]);
        DefaultAccountMapping::create(['mapping_type' => 'branch_liaison', 'category_key' => $agencyId,
            'debit_account_id' => $acc->id, 'credit_account_id' => $acc->id, 'status' => 1]);

        return (int) $acc->id;
    }

    /** Catch up the head office books (LedgerSync::sweep for a company's books). */
    public static function sweep(LedgerSync $sync): array
    {
        $company = self::company();
        $stats = [];
        if (! $company) {
            return $stats;
        }
        $bid = Ledger::businessId();
        $branches = DB::table('bus_agencies')->where('company_id', $company->id)->pluck('id')->all();
        $from = (string) (AccountingSetting::getValue('post_from_date') ?: '1970-01-01');
        $count = function ($r) use (&$stats) {
            $stats[$r] = ($stats[$r] ?? 0) + 1;
        };
        $moves = DB::table('bus_money_moves as mv')->whereIn('mv.agency_id', $branches)->where('mv.method', 'company')
            ->whereIn('mv.kind', [BusMoneyMove::SALE, BusMoneyMove::EXTRA])->whereNull('mv.skipped')->where('mv.at', '>=', $from)
            ->leftJoin('transaction_mappings as m', function ($j) use ($bid) {
                $j->on('m.transaction_id', '=', 'mv.id')->where('m.transaction_type', self::MOVE)->where('m.business_id', $bid);
            })->where(fn ($w) => $w->whereNull('m.id')->orWhere('m.status', 'failed'))->pluck('mv.id');
        foreach ($moves as $id) {
            $count(self::record($sync, self::MOVE, (int) $id));
        }
        $stmts = DB::table('bus_statements as s')->whereIn('s.agency_id', $branches)->whereNotNull('s.company_statement_id')
            ->where('s.status', BusStatement::PAID)->whereNotNull('s.paid_at')
            ->leftJoin('transaction_mappings as m', function ($j) use ($bid) {
                $j->on('m.transaction_id', '=', 's.id')->where('m.transaction_type', self::STATEMENT_PAID)->where('m.business_id', $bid);
            })->where(fn ($w) => $w->whereNull('m.id')->orWhere('m.status', 'failed'))->pluck('s.id');
        foreach ($stmts as $id) {
            $count(self::record($sync, self::STATEMENT_PAID, (string) $id));
        }

        return $stats;
    }

    private static function history($date): bool
    {
        $from = AccountingSetting::getValue('post_from_date');

        return $from && $date && substr((string) $date, 0, 10) < $from;
    }

    private static function mapping(string $type, string $key = ''): int
    {
        $id = DefaultAccount::id($type, $key);
        if (! $id) {
            throw new \RuntimeException(__('ledger.err_no_mapping', ['type' => __('ledger.map_'.$type), 'key' => $key]));
        }

        return (int) $id;
    }
}
