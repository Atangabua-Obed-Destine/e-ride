<?php

namespace Modules\ZoneManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\ZoneManagement\Service\SafetyService;

/** Every minute: planned pauses start (with a push) or are announced, old danger reports fade. */
class SafetyTick extends Command
{
    protected $signature = 'safety:tick {--date= : Run as if it were this date and time (testing)}';

    protected $description = 'Area pause and danger reports: start, announce and fade on time';

    public function handle(SafetyService $safety): int
    {
        $now = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $c = $safety->tick($now);
        $this->info(sprintf('%s: %d started, %d announced, %d reports faded', $now->toDateTimeString(), $c['started'], $c['announced'], $c['faded']));
        return self::SUCCESS;
    }
}
