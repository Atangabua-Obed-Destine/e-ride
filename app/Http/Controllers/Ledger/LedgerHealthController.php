<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Services\LedgerHealth;
use Illuminate\Http\Request;

/**
 * The ledger health check, on a screen (paxhitestbed Admin\LedgerHealthController).
 *
 * Read-only: LedgerHealth only queries. The one repair it offers, "Sync now",
 * posts through Transaction mappings' bulk sync (sweep=1) after the review
 * dialog, which is what the scheduler does every few minutes anyway.
 *
 * Guarded by general-ledger-view, as in paxhitestbed: it shows nothing that
 * reading the ledger would not already show.
 */
class LedgerHealthController extends LedgerController
{
    public function index(Request $request)
    {
        $this->allow('general-ledger-view');
        $health = new LedgerHealth();
        $checks = $health->checks();

        // One group at a time, for a screen that is long when everything is interesting.
        $group = $request->input('group');
        if ($group && in_array($group, LedgerHealth::GROUPS)) {
            $filtered = array_values(array_filter($checks, fn ($c) => $c['group'] === $group));
            if ($filtered !== []) {
                $checks = $filtered;
            }
        } else {
            $group = null;
        }

        return view('ledger.ledger-health.index', [
            'checks' => $checks,
            'findings' => array_values(array_filter($checks, fn ($c) => $c['state'] !== LedgerHealth::OK)),
            'passing' => array_values(array_filter($checks, fn ($c) => $c['state'] === LedgerHealth::OK)),
            'worst' => $health->worstState($checks),
            'concerns' => $health->concerns($checks),
            'groups' => LedgerHealth::GROUPS,
            'group' => $group,
            'status' => $health->status(),
            'checkedAt' => now(),
            'canSync' => auth()->user()->can('transaction-mapping-manage'),
        ]);
    }
}
