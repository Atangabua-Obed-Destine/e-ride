<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPushNotifications;
use Illuminate\Console\Command;
use Modules\TripManagement\Entities\TripRequest;

class ProcessScheduledTrips extends Command
{
    protected $signature = 'app:process-scheduled-trips';
    protected $description = 'Dispatch scheduled trips push notifications';

    public function handle()
    {
        if (!businessConfig('schedule_trip_status')?->value) {
            return;
        }

        $search_radius = (double)get_cache('search_radius');
        $scheduledTrips = TripRequest::where('ride_request_type', 'scheduled')
            ->where('current_status', PENDING)
            ->where('sending_notification_at', '<=', now())
            ->where('is_notification_sent', false)
            ->get();
        if ($scheduledTrips) {
            foreach ($scheduledTrips as $trip) {
                ProcessPushNotifications::dispatch(trip: $trip, radius: $search_radius);
                $trip->update(['is_notification_sent' => true]);
            }
        }
    }
}
