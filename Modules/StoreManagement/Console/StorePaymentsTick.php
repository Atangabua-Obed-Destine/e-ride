<?php

namespace Modules\StoreManagement\Console;

use Illuminate\Console\Command;
use Modules\StoreManagement\Service\StorePaymentService;
use Modules\StoreManagement\Service\StoreStatementService;

/**
 * Businesses paid into their own Fapshi: settle payments whose webhook never came, make the
 * weekly statements (Monday), and pause app orders of businesses too late paying one.
 */
class StorePaymentsTick extends Command
{
    protected $signature = 'store-payments:tick';
    protected $description = 'Own-Fapshi payments of Food & Shops businesses: reconcile, weekly statements, late pauses';

    public function handle(StorePaymentService $payments, StoreStatementService $statements): int
    {
        $counts = ['settled' => $payments->reconcile(), 'statements' => $statements->generate(), 'paused' => $statements->pauseLate()];
        if (array_sum($counts) > 0) {
            $this->line(json_encode($counts));
        }
        return self::SUCCESS;
    }
}
