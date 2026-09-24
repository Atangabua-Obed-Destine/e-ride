<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\UserManagement\Service\Interfaces\DriverAvailabilityScheduleServiceInterface;

class SyncDriverAvailability extends Command
{
    protected $signature = 'driver:sync-availability';
    protected $description = 'Sync driver online status with their availability schedule';

    public function handle(DriverAvailabilityScheduleServiceInterface $driverAvailabilityScheduleService)
    {
        $result = $driverAvailabilityScheduleService->syncAvailabilityWithSchedule();

        $this->info("Availability sync: {$result['offlined']} set offline, {$result['onlined']} set online.");
    }
}
