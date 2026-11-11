<?php

namespace Modules\TripManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\TripManagement\Service\TripCheckinService;

/** Every minute: watch rides in progress, ask "Are you OK?", escalate unanswered check-ins, share night rides. */
class TripCheckinTick extends Command
{
    protected $signature = 'trip-checkins:tick {--date= : Run as if it were this date and time (testing)}';

    protected $description = 'Trip check-ins and night sharing';

    public function handle(TripCheckinService $checkins): int
    {
        $now = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $c = $checkins->tick($now);
        $this->info(sprintf('%s: %d asked, %d escalated, %d closed, %d night rides shared',
            $now->toDateTimeString(), $c['asked'], $c['escalated'], $c['closed'], $c['night_shared']));
        return self::SUCCESS;
    }
}
