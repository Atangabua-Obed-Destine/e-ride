<?php

namespace App\Ledger\ERide;

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\TillCount;
use App\Ledger\Services\DefaultAccount;
use App\Ledger\Services\LedgerPoster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusStaff;

/**
 * Counter tills: the control a bus counter needs most (paxhitestbed's advice).
 *
 * Counter cash goes into the till account (the "cash" payment method's account,
 * 5712), each line tagged with the agent who took it. A count compares what the
 * agent hands over with what the ledger says their till holds, and posts:
 *
 *   Dr where the cash was put (571 safe, 521 bank...)   counted
 *   Dr 658 cash short                                    when counted < expected
 *        Cr 5712 the agent's till                        expected
 *        Cr 758 cash over                                when counted > expected
 *
 * A difference needs a note. A count is never deleted: voiding it reverses its entry.
 */
class Tills
{
    /** The till account (where counter cash goes). */
    public static function tillAccount(): ?int
    {
        return DefaultAccount::id('payment_method', 'cash');
    }

    /** What each agent's till holds now: [agent_id => amount], with the branch's counter staff even at 0. */
    public static function holdings(?string $upTo = null): array
    {
        $till = self::tillAccount();
        $out = [];
        $books = Books::current();
        if (($books['type'] ?? null) === Books::AGENCY) {
            foreach (BusStaff::where('agency_id', $books['owner']->id)->whereIn('role', [BusStaff::MANAGER, BusStaff::COUNTER])->pluck('user_id') as $uid) {
                $out[(string) $uid] = 0.0;
            }
        }
        if ($till) {
            $q = DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->where('l.business_id', Ledger::businessId())->where('l.account_id', $till)->whereNotNull('l.agent_id')
                ->where('e.is_posted', 1)->whereNull('e.deleted_at')
                ->when($upTo, fn ($w) => $w->where('e.entry_date', '<=', $upTo))
                ->groupBy('l.agent_id')->selectRaw('l.agent_id, SUM(l.debit) - SUM(l.credit) as bal');
            foreach ($q->pluck('bal', 'agent_id') as $agent => $bal) {
                $out[(string) $agent] = round((float) $bal, 2);
            }
        }

        return $out;
    }

    /** Names of the agents. */
    public static function names(array $ids): array
    {
        return \Modules\UserManagement\Entities\User::whereIn('id', $ids)->get()
            ->mapWithKeys(fn ($u) => [(string) $u->id => trim($u->first_name.' '.$u->last_name) ?: $u->email])->all();
    }

    /** Where counted cash can be put: the class-5 accounts that can be posted to, but not the tills. */
    public static function destinations()
    {
        return ChartOfAccount::postable()->where('class_number', 5)->where('id', '!=', (int) self::tillAccount())
            ->orderBy('account_code')->get();
    }

    /** Count an agent's till and post it. */
    public static function count(string $agentId, float $counted, int $toAccountId, string $date, ?string $note, ?string $userId): TillCount
    {
        $till = self::tillAccount();
        if (! $till) {
            throw new \RuntimeException(__('ledger.err_no_mapping', ['type' => __('ledger.map_payment_method'), 'key' => 'cash']));
        }
        $holdings = self::holdings($date);
        if (! array_key_exists($agentId, $holdings)) {
            throw new \RuntimeException(__('ledger_tills.err_not_agent'));
        }
        $expected = round((float) $holdings[$agentId], 2);
        $counted = round($counted, 2);
        $diff = round($counted - $expected, 2);
        if ($counted < 0) {
            throw new \RuntimeException(__('ledger_tills.err_negative'));
        }
        if ($counted == 0 && $expected == 0) {
            throw new \RuntimeException(__('ledger_tills.err_nothing'));
        }
        if (abs($diff) >= 0.01 && trim((string) $note) === '') {
            throw new \RuntimeException(__('ledger_tills.err_note_needed'));
        }
        if (! self::destinations()->contains('id', $toAccountId)) {
            throw new \RuntimeException(__('ledger_tills.err_destination'));
        }
        $tag = ['agent_id' => $agentId];
        $lines = [];
        if ($counted > 0) {
            $lines[] = ['account_id' => $toAccountId, 'debit' => $counted, 'credit' => 0, 'description' => __('ledger_tills.l_counted')];
        }
        if ($expected != 0) {
            $lines[] = ['account_id' => $till, 'debit' => max(0, -$expected), 'credit' => max(0, $expected), 'description' => __('ledger_tills.l_till')] + $tag;
        }
        if ($diff < 0) {
            $lines[] = ['account_id' => self::mapping('till_short'), 'debit' => -$diff, 'credit' => 0, 'description' => __('ledger_tills.l_short')] + $tag;
        } elseif ($diff > 0) {
            $lines[] = ['account_id' => self::mapping('till_over'), 'debit' => 0, 'credit' => $diff, 'description' => __('ledger_tills.l_over')] + $tag;
        }
        $name = self::names([$agentId])[$agentId] ?? '';

        return DB::transaction(function () use ($agentId, $expected, $counted, $diff, $toAccountId, $date, $note, $userId, $lines, $name) {
            $count = TillCount::create([
                'agent_id' => $agentId, 'count_date' => $date, 'expected' => $expected, 'counted' => $counted, 'difference' => $diff,
                'to_account_id' => $toAccountId, 'note' => $note, 'status' => 'posted', 'counted_by' => $userId,
            ]);
            $entry = LedgerPoster::post([
                'entry_date' => $date,
                'journal_type' => 'cash',
                'description' => __('ledger_tills.entry_desc', ['agent' => $name]).($note ? ' ('.$note.')' : ''),
                'reference_type' => 'till_count',
                'reference_id' => $count->id,
                'reference_number' => 'TILL-'.$count->id,
                'is_system_generated' => true,
            ], $lines, $userId);
            $count->forceFill(['journal_entry_id' => $entry->id])->save();

            return $count;
        });
    }

    /** Void a count: its entry is reversed (never deleted). */
    public static function void(TillCount $count, string $reason, ?string $userId): void
    {
        if ($count->status !== 'posted') {
            throw new \RuntimeException(__('ledger_tills.err_already_void'));
        }
        DB::transaction(function () use ($count, $reason, $userId) {
            $entry = $count->journalEntry;
            if ($entry && $entry->is_posted && ! $entry->is_reversed) {
                LedgerPoster::reverse($entry, 'till_count_void', $count->id, $userId, $reason);
            }
            $count->forceFill(['status' => 'void', 'void_reason' => $reason, 'voided_by' => $userId, 'voided_at' => now()])->save();
        });
    }

    private static function mapping(string $type): int
    {
        $id = DefaultAccount::id($type);
        if (! $id) {
            throw new \RuntimeException(__('ledger.err_no_mapping', ['type' => __('ledger.map_'.$type), 'key' => '']));
        }

        return (int) $id;
    }
}
