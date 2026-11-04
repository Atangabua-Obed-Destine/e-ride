<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\TripManagement\Service\TripDepositService;

/**
 * Every 15 seconds: cancel accepted trips whose booking deposit was not paid
 * in time, releasing their drivers. A MoMo payment still in progress is
 * checked with Fapshi first and given a little longer.
 */
class TripDepositsTick extends Command
{
    protected $signature = 'trip-deposits:tick';
    protected $description = 'Expire unpaid booking deposits and release their drivers';

    public function handle(TripDepositService $deposits): int
    {
        $counts = $deposits->tick();
        if (array_sum($counts) > 0) {
            $this->line(json_encode($counts));
        }
        return self::SUCCESS;
    }
}
