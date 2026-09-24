<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $userIds = DB::table('driver_availability_schedules')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) = 1')
            ->havingRaw("SUM(day_of_week = 0 AND start_time = '00:00:00' AND end_time = '23:59:59') = 1")
            ->pluck('user_id');

        foreach ($userIds->chunk(500) as $chunk) {
            DB::table('driver_details')
                ->where('same_time_for_every_day', 0)
                ->whereIn('user_id', $chunk)
                ->update(['same_time_for_every_day' => 1]);
        }
    }

    public function down(): void
    {
    }
};
