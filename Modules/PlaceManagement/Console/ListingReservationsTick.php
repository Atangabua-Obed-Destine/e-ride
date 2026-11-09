<?php

namespace Modules\PlaceManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\PlaceManagement\Service\ListingReservationService;

/**
 * Homes & spaces reservations, every minute: unpaid ones lapse, owners who did
 * not answer in time refund the seeker, move-in pushes, automatic release.
 */
class ListingReservationsTick extends Command
{
    protected $signature = 'listing-reservations:tick {--date= : Run as if it were this date and time (testing)}';

    protected $description = 'Homes & spaces: apply reservation deadlines';

    public function handle(ListingReservationService $reservations): int
    {
        $now = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $c = $reservations->tick($now);
        $this->info(sprintf('%s: %d lapsed, %d refunded (no answer), %d move-in pushes, %d released',
            $now->toDateTimeString(), $c['lapsed'], $c['no_answer'], $c['move_in'], $c['released']));
        return self::SUCCESS;
    }
}
