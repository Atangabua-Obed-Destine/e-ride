<?php

namespace Modules\TripManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\TripManagement\Service\ScheduledRideService;

/** Every minute: scheduled rides' deposits before the pickup, safety heads-ups, "still looking", no driver. */
class ScheduledRideTick extends Command
{
    protected $signature = 'scheduled-rides:tick {--date= : Run as if it were this date and time (testing)}';

    protected $description = 'Scheduled rides: deposit before pickup, safety heads-ups, still looking, no driver';

    public function handle(ScheduledRideService $rides): int
    {
        $now = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $c = $rides->tick($now);
        $this->info(sprintf('%s: %d deposits asked, %d safety alerts, %d still looking, %d cancelled with no driver',
            $now->toDateTimeString(), $c['deposits_asked'], $c['alerts'], $c['still_looking'], $c['no_driver']));
        return self::SUCCESS;
    }
}
