<?php

namespace Modules\PlaceManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\PlaceManagement\Service\ListingService;

/**
 * Homes & spaces, once a day: the "still available" policy (reminders,
 * pausing, the check after "already taken" reports), nudges, verified badges
 * expiring, and visit reminders. --date runs it as if it were that day.
 */
class ListingsSweep extends Command
{
    protected $signature = 'listings:sweep {--date= : Run as if it were this date and time (testing)}';

    protected $description = 'Homes & spaces: apply the still-available policy and send reminders';

    public function handle(ListingService $listings): int
    {
        $now = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $counts = $listings->sweep($now);
        $this->info(sprintf('%s: %d reminded, %d paused, %d nudged, %d verified badges expired',
            $now->toDateTimeString(), $counts['reminded'], $counts['paused'], $counts['nudged'], $counts['expired']));
        return self::SUCCESS;
    }
}
