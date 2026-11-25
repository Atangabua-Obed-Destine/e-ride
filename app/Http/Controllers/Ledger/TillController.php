<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\ERide\Tills;
use App\Ledger\Models\TillCount;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Counter tills (E-Ride, bus books; App\Ledger\ERide\Tills): what each counter
 * agent's till holds, counting it through the review dialog, and the counts
 * made, each voidable by reversal.
 */
class TillController extends LedgerController
{
    public function index(Request $request)
    {
        $this->allow('till-count-view');
        $holdings = Tills::holdings();
        $counts = TillCount::with(['journalEntry:id,entry_number', 'toAccount:id,account_code,account_name'])
            ->orderByDesc('count_date')->orderByDesc('id')->paginate(30);
        $names = Tills::names(array_unique(array_merge(array_keys($holdings), $counts->pluck('agent_id')->all())));
        $last = TillCount::where('status', 'posted')->whereIn('agent_id', array_keys($holdings))
            ->selectRaw('agent_id, MAX(count_date) as d')->groupBy('agent_id')->pluck('d', 'agent_id');

        return view('ledger.tills.index', [
            'holdings' => $holdings,
            'names' => $names,
            'last' => $last,
            'counts' => $counts,
            'destinations' => Tills::destinations(),
            'safe' => \App\Ledger\Services\DefaultAccount::code('571'),
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    public function count(Request $request)
    {
        $this->allow('till-count-create');
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $data = $request->validate([
            'agent_id' => 'required|string|max:36',
            'counted' => 'required|numeric|min:0',
            'to_account_id' => 'required|integer',
            'count_date' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:500',
        ]);
        try {
            Tills::count($data['agent_id'], (float) $data['counted'], (int) $data['to_account_id'], Carbon::parse($data['count_date'])->toDateString(), $data['note'] ?? null, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.tills.index'), __('ledger_tills.counted_ok'));
    }

    public function void(Request $request, $id)
    {
        $this->allow('till-count-void');
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $count = TillCount::findOrFail((int) $id);
        try {
            Tills::void($count, $data['reason'], auth()->id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.tills.index'), __('ledger_tills.voided_ok'));
    }
}
