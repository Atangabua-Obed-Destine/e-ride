<?php

namespace Modules\HotelManagement\Console;

use Illuminate\Console\Command;
use Modules\HotelManagement\Service\InquiryService;
use Modules\HotelManagement\Service\StayService;

/** Every minute: kept rooms past their time go back on sale; old questions and bookings close. */
class HotelTick extends Command
{
    protected $signature = 'hotel:tick';

    protected $description = 'Hotel partners: let go of kept rooms past their time, end bookings never started, close old questions';

    public function handle(StayService $stays, InquiryService $inquiries): int
    {
        $done = $stays->tick();
        $closed = $inquiries->tick();
        if ($done['released'] || $done['ended'] || $closed) {
            $this->info("released {$done['released']}, ended {$done['ended']}, closed {$closed}");
        }
        return self::SUCCESS;
    }
}
