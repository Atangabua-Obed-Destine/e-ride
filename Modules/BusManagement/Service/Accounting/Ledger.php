<?php

namespace Modules\BusManagement\Service\Accounting;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAccAccount;
use Modules\BusManagement\Entities\BusAccBook;
use Modules\BusManagement\Entities\BusAccEntry;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompany;
use Modules\BusManagement\Lib\AccChart;

/**
 * The one way into a branch's books. Every entry, automatic or by hand, goes through post():
 *  - at least two lines, each a debit or a credit (never both), debits equal to credits;
 *  - accounts of this branch, active;
 *  - a date on or after the books' start, not in the future, in an open month. An automatic
 *    posting whose month is closed lands today instead (never in a closed month, never lost);
 *    a posting by hand is refused with the reason.
 *  - one posting per source event (source_key, unique in the database).
 * Nothing posted is ever edited or deleted: reverse() posts the mirror entry, dated in the
 * original's month when that month is open, otherwise today.
 */
class Ledger
{
    public const TAGS = ['departure_id', 'vehicle_id', 'destination_id', 'channel', 'staff_user_id'];

    public function books(BusAgency|BusCompany|string $agency): ?BusAccBook
    {
        return BusAccBook::where('agency_id', $this->id($agency))->first();
    }

    public function account(BusAgency|BusCompany|string $agency, string $role): BusAccAccount
    {
        $a = BusAccAccount::where('agency_id', $this->id($agency))->where('role', $role)->first();
        if (!$a) {
            throw new \RuntimeException("The books have no '$role' account.");
        }
        return $a;
    }

    /** Creates the chart of accounts (on opening the books, and when new accounts join the standard chart). */
    public function seedChart(BusAgency|BusCompany $owner): void
    {
        $chart = $owner instanceof BusCompany ? AccChart::COMPANY_ACCOUNTS : AccChart::ACCOUNTS;
        foreach ($chart as [$code, $name, $class, $type, $money, $role, $category]) {
            BusAccAccount::firstOrCreate(['agency_id' => (string)$owner->id, 'code' => $code],
                ['name' => $name, 'class' => $class, 'type' => $type, 'money' => $money, 'role' => $role, 'category' => $category, 'active' => true]);
        }
        if ($owner instanceof BusCompany) {
            foreach (BusAgency::where('company_id', $owner->id)->orderBy('created_at')->get() as $branch) {
                $this->branchAccount($owner, $branch);
            }
        }
    }

    /**
     * On a head office's books, the account of one branch (OHADA 18, liaison): what the head office
     * holds for that branch. It mirrors the branch's own 1811, so the two always agree.
     */
    public function branchAccount(BusCompany|string $company, BusAgency $branch): BusAccAccount
    {
        $companyId = $company instanceof BusCompany ? (string)$company->id : $company;
        $a = BusAccAccount::where('agency_id', $companyId)->where('role', 'branch')->where('link_id', $branch->id)->first();
        if ($a) {
            return $a;
        }
        $n = BusAccAccount::where('agency_id', $companyId)->where('role', 'branch')->count() + 1;
        return BusAccAccount::create(['agency_id' => $companyId, 'code' => '181' . str_pad((string)$n, 2, '0', STR_PAD_LEFT), 'name' => $branch->branchLabel(),
            'class' => 1, 'type' => 'liability', 'money' => false, 'role' => 'branch', 'link_id' => (string)$branch->id, 'category' => false, 'active' => true]);
    }

    public function monthClosed(string $agencyId, CarbonInterface|string $date): bool
    {
        if ($this->yearClosed($agencyId, $date)) {
            return true;
        }
        $month = Carbon::parse($date)->startOfMonth()->toDateString();
        return DB::table('bus_acc_months')->where('agency_id', $agencyId)->where('month', $month)->whereNotNull('closed_at')->exists();
    }

    public function yearClosed(string $agencyId, CarbonInterface|string $date): bool
    {
        return DB::table('bus_acc_years')->where('agency_id', $agencyId)->where('year', Carbon::parse($date)->year)->whereNotNull('closed_at')->exists();
    }

    /**
     * Posts an entry. $e: date, journal, memo, lines [[account, debit, credit, memo?, tags...]],
     * source_type?, source_id?, source_key?, created_by?, auto (bool: a closed month lands today), reverses_id?
     */
    public function post(BusAgency|BusCompany|string $agency, array $e): BusAccEntry
    {
        $agencyId = $this->id($agency);
        return DB::transaction(function () use ($agencyId, $e) {
            // The books row is the lock: entry numbers follow each other without gaps or doubles.
            $books = BusAccBook::where('agency_id', $agencyId)->lockForUpdate()->first();
            if (!$books) {
                throw ValidationException::withMessages(['books' => translate('Open the books first.')]);
            }
            $auto = (bool)($e['auto'] ?? false);
            $date = Carbon::parse($e['date'])->startOfDay();
            if ($date->lt($books->starts_on)) {
                throw ValidationException::withMessages(['date' => translate(key: 'The books start on {date}: nothing can be dated before.', replace: ['date' => $books->starts_on->format('d/m/Y')])]);
            }
            if ($date->gt(today())) {
                throw ValidationException::withMessages(['date' => translate('The date cannot be in the future.')]);
            }
            if (!empty($e['closing'])) {
                // The year-end closing entry: dated the last day of a year whose months are all closed.
                if ($this->yearClosed($agencyId, $date)) {
                    throw ValidationException::withMessages(['date' => translate('This year is already closed.')]);
                }
            } elseif ($this->monthClosed($agencyId, $date)) {
                if (!$auto) {
                    throw ValidationException::withMessages(['date' => translate(key: '{month} is closed. Date it in an open month, or reopen the month first.', replace: ['month' => $date->translatedFormat('F Y')])]);
                }
                $date = today();
                if ($this->monthClosed($agencyId, $date)) {
                    throw ValidationException::withMessages(['date' => translate('This month is closed: reopen it so new tickets and refunds can post.')]);
                }
            }

            $lines = [];
            $debit = 0.0;
            $credit = 0.0;
            $ids = [];
            foreach ($e['lines'] as $l) {
                $account = $l[0] instanceof BusAccAccount ? $l[0] : BusAccAccount::find($l[0]);
                $d = round((float)($l[1] ?? 0), 2);
                $c = round((float)($l[2] ?? 0), 2);
                if ($d == 0.0 && $c == 0.0) {
                    continue;
                }
                if (!$account || (string)$account->agency_id !== (string)$agencyId) {
                    throw ValidationException::withMessages(['lines' => translate('An account does not belong to this branch.')]);
                }
                if (!$account->active) {
                    throw ValidationException::withMessages(['lines' => translate(key: 'The account {account} is switched off.', replace: ['account' => $account->label()])]);
                }
                if ($d < 0 || $c < 0 || ($d > 0 && $c > 0)) {
                    throw ValidationException::withMessages(['lines' => translate('Each line is a debit or a credit, above zero.')]);
                }
                $debit += $d;
                $credit += $c;
                $ids[] = (string)$account->id;
                $line = ['account_id' => (string)$account->id, 'debit' => $d, 'credit' => $c, 'memo' => isset($l['memo']) ? mb_substr((string)$l['memo'], 0, 200) : null];
                foreach (self::TAGS as $tag) {
                    $line[$tag] = isset($l[$tag]) && $l[$tag] !== '' ? (string)$l[$tag] : null;
                }
                $lines[] = $line;
            }
            if (count($lines) < 2) {
                throw ValidationException::withMessages(['lines' => translate('An entry needs at least two lines.')]);
            }
            if (abs($debit - $credit) > 0.004) {
                throw ValidationException::withMessages(['lines' => translate(key: 'Debits ({debit}) and credits ({credit}) must be equal.', replace: ['debit' => number_format($debit, 0, '.', ' '), 'credit' => number_format($credit, 0, '.', ' ')])]);
            }

            $number = 'JE-' . str_pad((string)$books->next_number, 6, '0', STR_PAD_LEFT);
            $books->increment('next_number');
            $entry = BusAccEntry::create([
                'agency_id' => $agencyId,
                'number' => $number,
                'date' => $date->toDateString(),
                'journal' => $e['journal'] ?? 'general',
                'memo' => mb_substr((string)$e['memo'], 0, 250),
                'source_type' => $e['source_type'] ?? null,
                'source_id' => isset($e['source_id']) ? (string)$e['source_id'] : null,
                'source_key' => $e['source_key'] ?? null,
                'total' => round($debit, 2),
                'reverses_id' => $e['reverses_id'] ?? null,
                'created_by' => $e['created_by'] ?? null,
            ]);
            foreach ($lines as $line) {
                BusAccLine::create($line + ['entry_id' => (string)$entry->id, 'agency_id' => $agencyId, 'date' => $date->toDateString()]);
            }
            return $entry;
        });
    }

    /**
     * Mirrors an entry (debits become credits), dated in the original's month when it is open,
     * otherwise today. An entry is reversed once.
     */
    public function reverse(BusAccEntry $entry, string $memo, ?string $userId = null, ?string $date = null, ?string $sourceKey = null): BusAccEntry
    {
        return DB::transaction(function () use ($entry, $memo, $userId, $date, $sourceKey) {
            $original = BusAccEntry::whereKey($entry->id)->lockForUpdate()->first();
            if ($original->reversed_by_id) {
                throw ValidationException::withMessages(['entry' => translate(key: 'Entry {number} is already reversed.', replace: ['number' => $original->number])]);
            }
            $when = $date ? Carbon::parse($date) : ($this->monthClosed($original->agency_id, $original->date) ? today() : $original->date);
            if ($when->gt(today())) {
                $when = today();
            }
            $lines = $original->lines->map(fn(BusAccLine $l) => [$l->account_id, $l->credit, $l->debit, 'memo' => $l->memo]
                + collect(self::TAGS)->mapWithKeys(fn($t) => [$t => $l->{$t}])->all())->all();
            $reversal = $this->post($original->agency_id, [
                'date' => $when, 'journal' => $original->journal, 'memo' => $memo, 'lines' => $lines, 'auto' => true,
                'source_type' => $original->source_type, 'source_id' => $original->source_id, 'source_key' => $sourceKey,
                'reverses_id' => (string)$original->id, 'created_by' => $userId,
            ]);
            $original->update(['reversed_by_id' => (string)$reversal->id]);
            return $reversal;
        });
    }

    /** Balance of an account (in its normal direction) from its posted lines, optionally up to a date and for one tag. */
    public function balance(BusAccAccount $account, ?string $upTo = null, array $tags = []): float
    {
        $q = BusAccLine::where('agency_id', $account->agency_id)->where('account_id', $account->id);
        if ($upTo) {
            $q->where('date', '<=', $upTo);
        }
        foreach ($tags as $k => $v) {
            $v === null ? $q->whereNull($k) : $q->where($k, $v);
        }
        $row = $q->selectRaw('coalesce(sum(debit),0) d, coalesce(sum(credit),0) c')->first();
        $net = (float)$row->d - (float)$row->c;
        return round($account->debitNormal() ? $net : -$net, 2);
    }

    /** The next document number of a type (EXP-00001), counted per type under the books lock. */
    public function nextDocumentNumber(string $agencyId, string $type): string
    {
        BusAccBook::where('agency_id', $agencyId)->lockForUpdate()->first();
        $n = \Modules\BusManagement\Entities\BusAccDocument::where('agency_id', $agencyId)->where('type', $type)->count() + 1;
        return \Modules\BusManagement\Entities\BusAccDocument::PREFIX[$type] . '-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
    }

    protected function id(BusAgency|BusCompany|string $agency): string
    {
        return is_string($agency) ? $agency : (string)$agency->id;
    }
}
