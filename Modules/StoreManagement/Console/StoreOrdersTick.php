<?php

namespace Modules\StoreManagement\Console;

use Illuminate\Console\Command;
use Modules\StoreManagement\Service\Interfaces\StoreOrderServiceInterface;

/**
 * Every minute: expire unpaid orders, refund orders the store never answered,
 * call riders when food is nearly ready, and re-sync orders with their
 * delivery trips (a backstop for trip changes made without model events, such
 * as the bulk auto-cancel of pending trips).
 */
class StoreOrdersTick extends Command
{
    protected $signature = 'store-orders:tick';
    protected $description = 'Advance Food & Shops orders: timeouts, rider requests and trip sync';

    public function handle(StoreOrderServiceInterface $orders): int
    {
        $counts = $orders->tick();
        if (array_sum($counts) > 0) {
            $this->line(json_encode($counts));
        }
        return self::SUCCESS;
    }
}
