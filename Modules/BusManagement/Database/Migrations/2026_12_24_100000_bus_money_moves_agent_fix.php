<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusAccEntry;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Service\Accounting\Ledger;

/**
 * The first backfill of money moves lost the counter agent on cash sales and cash refunds (an array
 * union kept the empty value). Puts the agent back on those moves and, where one was already posted,
 * moves its counter cash to the agent with a reclassifying entry (Dr/Cr 5711 between "no agent" and
 * the agent). The posted lines themselves are never edited.
 */
return new class extends Migration {
    public function up(): void
    {
        $moves = DB::table('bus_money_moves as m')->join('bus_bookings as b', 'b.id', '=', 'm.booking_id')
            ->where('m.backfilled', true)->where('m.channel', 'counter')->whereIn('m.kind', ['sale', 'cash_refund'])->where('m.method', 'cash')
            ->whereNull('m.staff_user_id')->whereNotNull('b.staff_user_id')
            ->select('m.id', 'm.agency_id', 'm.kind', 'm.amount', 'm.entry_id', 'b.staff_user_id as agent', 'b.code')->get();
        $ledger = app(Ledger::class);
        foreach ($moves as $m) {
            DB::transaction(function () use ($m, $ledger) {
                DB::table('bus_money_moves')->where('id', $m->id)->update(['staff_user_id' => $m->agent]);
                if (!$m->entry_id || !($entry = BusAccEntry::find($m->entry_id))) {
                    return;
                }
                $line = BusAccLine::where('entry_id', $entry->id)->whereNull('staff_user_id')
                    ->whereIn('account_id', DB::table('bus_acc_accounts')->where('agency_id', $m->agency_id)->where('role', 'counter_cash')->pluck('id'))->first();
                if (!$line || BusAccEntry::where('agency_id', $m->agency_id)->where('source_key', 'agentfix:' . $m->id)->exists()) {
                    return;
                }
                $amount = round((float)$line->debit - (float)$line->credit, 2);
                // The same movement, now on the agent: in to the agent, out of "no agent" (or the reverse for a refund).
                $ledger->post($m->agency_id, [
                    'date' => today(), 'journal' => 'cash', 'auto' => true,
                    'memo' => 'Counter cash put on its agent · ' . $m->code,
                    'lines' => $amount > 0
                        ? [[$line->account_id, $amount, 0, 'staff_user_id' => $m->agent], [$line->account_id, 0, $amount]]
                        : [[$line->account_id, -$amount, 0], [$line->account_id, 0, -$amount, 'staff_user_id' => $m->agent]],
                    'source_type' => 'move', 'source_id' => (string)$m->id, 'source_key' => 'agentfix:' . $m->id,
                ]);
            });
        }
    }

    public function down(): void
    {
    }
};
