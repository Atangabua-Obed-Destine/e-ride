<?php

namespace Modules\PlaceManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\PlaceManagement\Service\ListingService;

/** Homes & spaces: the reminder about 2 hours before a visit. Runs every 10 minutes. */
class ListingsVisitSoon extends Command
{
    protected $signature = 'listings:visit-soon {--date= : Run as if it were this date and time (testing)}';

    protected $description = 'Homes & spaces: remind both sides about 2 hours before a visit';

    public function handle(ListingService $listings): int
    {
        $now = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $this->info(sprintf('%s: %d visits reminded', $now->toDateTimeString(), $listings->remindSoon($now)));
        return self::SUCCESS;
    }
}
