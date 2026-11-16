<?php

namespace Modules\BusManagement\Console;

use Illuminate\Console\Command;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Service\AgencyService;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Service\BusPaymentService;
use Modules\BusManagement\Service\ScheduleService;

/**
 * Every minute: payments whose webhook never came are settled, unanswered requests and unpaid acceptances
 * end (their seats free up), departures nobody marked departed close. Every 15 minutes: the timetable opens
 * the coming days, and agencies that just became ready go live.
 */
class BusTick extends Command
{
    protected $signature = 'bus:tick {--open : Open the coming days of the timetable now}';
    protected $description = 'E-Ride Bus Travel: settle payments, expire requests, open departures';

    public function handle(BusPaymentService $payments, BookingService $bookings, ScheduleService $schedule, AgencyService $agencies): int
    {
        // Payments first, so a booking paid at the last second is not expired.
        $counts = ['settled' => $payments->reconcile()];
        $counts += $bookings->expireDue();
        $counts['closed'] = $bookings->closePast();
        if ($this->option('open') || now()->minute % 15 === 0) {
            $counts['opened'] = $schedule->openAhead();
            BusAgency::where('status', BusAgency::SETUP)->whereNotNull('fapshi_ok_at')->get()->each(fn($a) => $agencies->refreshStatus($a));
        }
        if (array_sum($counts) > 0) {
            $this->line(json_encode($counts));
        }
        return self::SUCCESS;
    }
}
