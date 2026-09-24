<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\UserManagement\Service\Interfaces\DriverDetailServiceInterface;

class ResumePausedDrivers extends Command
{
    protected $signature = 'driver:resume-paused';
    protected $description = 'Auto resume drivers whose admin pause duration has ended';

    public function handle(DriverDetailServiceInterface $driverDetailService)
    {
        $count = $driverDetailService->resumeExpiredPauses();

        $this->info("Resumed {$count} driver(s).");
    }
}
