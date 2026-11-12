<?php

namespace Modules\TripManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\TripManagement\Service\LostItemService;

/** Every five minutes: remind drivers, hand unanswered or stuck lost items to support, close returned ones. */
class LostItemTick extends Command
{
    protected $signature = 'lost-items:tick {--date= : Run as if it were this date and time (testing)}';

    protected $description = 'Lost and found follow-up';

    public function handle(LostItemService $items): int
    {
        $now = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $c = $items->tick($now);
        $this->info(sprintf('%s: %d reminded, %d escalated, %d closed', $now->toDateTimeString(), $c['reminded'], $c['escalated'], $c['closed']));
        return self::SUCCESS;
    }
}
