<?php

namespace Modules\MarketManagement\Console;

use Illuminate\Console\Command;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\MarketOrderService;

/** Every minute: unanswered order requests expire, unagreed changes are cancelled, auctions alert and end, bookings expire and remind. */
class MarketOrdersTick extends Command
{
    protected $signature = 'market-orders:tick';
    protected $description = 'E-Ride Market orders: expire unanswered requests and unagreed changes';

    public function handle(MarketOrderService $orders, \Modules\MarketManagement\Service\AuctionService $auctions,
                           \Modules\MarketManagement\Service\BookingService $bookings): int
    {
        // Orders already sent still close on time, even if the market was switched off since.
        $counts = $orders->tick() + $auctions->tick();
        foreach ($bookings->tick() as $key => $n) {
            $counts['bookings_' . $key] = $n;
        }
        // E-Ride Jobs: jobs past their date close, unanswered applications close kindly.
        $counts += app(\Modules\MarketManagement\Service\JobService::class)->tick();
        if (array_sum($counts) > 0) {
            $this->line(json_encode($counts + ['market_on' => MarketSettings::enabled()]));
        }
        return self::SUCCESS;
    }
}
