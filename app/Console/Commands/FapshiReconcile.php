<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Gateways\Entities\FapshiTransaction;
use Modules\Gateways\Services\FapshiPayments;

/**
 * Settles Fapshi payments whose webhook never arrived. Fapshi sends a webhook
 * once and never retries, so a customer who approved on their phone and then
 * closed the app would otherwise stay unpaid.
 */
class FapshiReconcile extends Command
{
    protected $signature = 'fapshi:reconcile {--limit=50 : Most transactions to check in one run}';
    protected $description = 'Re-check open Fapshi mobile money payments and settle the ones that were paid';

    public function handle(FapshiPayments $payments): int
    {
        if (!$payments->client()->isConfigured()) {
            return self::SUCCESS;
        }

        $open = FapshiTransaction::whereIn('status', FapshiTransaction::OPEN)
            ->whereNotNull('trans_id')
            ->where('initiated_at', '>', now()->subHours(48))
            ->where(fn($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinute()))
            ->oldest('last_checked_at')
            ->limit((int)$this->option('limit'))
            ->get();

        $settled = 0;
        foreach ($open as $transaction) {
            $status = $payments->sync($transaction);
            if ($status !== $transaction->status) {
                $this->line("{$transaction->external_id}: {$transaction->status} -> {$status}");
            }
            $settled += $status === FapshiTransaction::SUCCESSFUL ? 1 : 0;
        }

        if ($open->isNotEmpty()) {
            $this->info("Checked {$open->count()}, settled {$settled}.");
        }
        return self::SUCCESS;
    }
}
