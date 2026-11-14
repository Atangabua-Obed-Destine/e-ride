<?php

namespace Modules\MarketManagement\Console;

use Illuminate\Console\Command;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\MarketOrderService;

/** Every minute: Market order requests nobody answered expire; changes nobody agreed to are cancelled. */
class MarketOrdersTick extends Command
{
    protected $signature = 'market-orders:tick';
    protected $description = 'E-Ride Market orders: expire unanswered requests and unagreed changes';

    public function handle(MarketOrderService $orders): int
    {
        // Orders already sent still close on time, even if the market was switched off since.
        $counts = $orders->tick();
        if (array_sum($counts) > 0) {
            $this->line(json_encode($counts + ['market_on' => MarketSettings::enabled()]));
        }
        return self::SUCCESS;
    }
}
