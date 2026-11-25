<?php

namespace App\Ledger;

use App\Ledger\ERide\Books;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\PostingRules;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusMoneyMove;
use Modules\BusManagement\Entities\BusStatement;

/**
 * Watches the bus records that post (E-Ride's version of posinnova's observer
 * on sales, purchases and payments; paxhitestbed's Fee / Expense / Income
 * observers): a money move written, a trip changing (it leaves, is cancelled,
 * runs again), E-Ride's statement made or paid. A save or delete queues the
 * record; it is posted once the request's own database work is done
 * (LedgerSync), so selling a ticket never waits for, or fails because of, the
 * books. Changes made without Eloquent are caught by the scheduled `ledger:sync`.
 */
class LedgerObserver
{
    /** branch id => ledger business id (or 0: no books), for this request */
    private static $books = [];

    public function saved($model)
    {
        $this->queue($model);
    }

    public function deleted($model)
    {
        $this->queue($model);
    }

    private function queue($model): void
    {
        try {
            if (empty($model->id) || empty($model->agency_id)) {
                return;
            }
            $bid = $this->businessOf((string) $model->agency_id);
            if (! $bid) {
                return;
            }
            if ($model instanceof BusMoneyMove) {
                LedgerSync::queue(PostingRules::MOVE, (int) $model->id, $bid);
            } elseif ($model instanceof BusDeparture) {
                if (! $model->exists || $model->wasRecentlyCreated || $model->wasChanged(['status', 'departed_at', 'date', 'time', 'delayed_to'])) {
                    LedgerSync::queue(PostingRules::DEPARTURE, (string) $model->id, $bid);
                }
            } elseif ($model instanceof BusStatement) {
                LedgerSync::queue(PostingRules::STATEMENT, (string) $model->id, $bid);
                LedgerSync::queue(PostingRules::STATEMENT_PAID, (string) $model->id, $bid);
            }
        } catch (\Throwable $e) {
            \Log::error('Ledger observer: '.$e->getMessage());
        }
    }

    /** The books of a bus branch, when it keeps them in the accounting module. */
    private function businessOf(string $agencyId): int
    {
        if (empty(self::$books[$agencyId])) {
            // only a found set of books is remembered: books opened later in the same process are seen
            self::$books[$agencyId] = (int) DB::table('ledger_businesses')->where('owner_type', Books::AGENCY)->where('owner_id', $agencyId)->value('id');
        }

        return self::$books[$agencyId];
    }

    /** Tests: forget what was looked up. */
    public static function forget(): void
    {
        self::$books = [];
    }
}
