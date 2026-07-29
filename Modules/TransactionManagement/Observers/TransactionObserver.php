<?php

namespace Modules\TransactionManagement\Observers;

use Modules\TransactionManagement\Entities\Transaction;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        $latestRole = Transaction::whereNotNull('readable_id')->orderBy('readable_id', 'desc')->first();
        if ($latestRole) {
            $latestId = (int)$latestRole->readable_id;
            $newId = $latestId + 1;
        } else {
            $newId = 10000000;
        }
        // Set the new readable_id
        $transaction->readable_id = $newId;
        $transaction->save();
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "force deleted" event.
     */
    public function forceDeleted(Transaction $transaction): void
    {
        //
    }
}
