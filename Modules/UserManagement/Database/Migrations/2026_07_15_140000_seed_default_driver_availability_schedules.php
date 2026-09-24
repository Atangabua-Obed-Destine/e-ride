<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('driver_availability_schedules')
            ->where('day_of_week', '!=', 0)
            ->whereIn('user_id', fn($query) => $query->select('user_id')->from('driver_details')->where('same_time_for_every_day', 1))
            ->delete();

        $userIds = DB::table('driver_details')
            ->whereNotIn('user_id', fn($query) => $query->select('user_id')->from('driver_availability_schedules'))
            ->pluck('user_id');

        foreach ($userIds->chunk(500) as $chunk) {
            DB::table('driver_availability_schedules')->insert($chunk->map(fn($userId) => [
                'id' => Str::uuid()->toString(),
                'user_id' => $userId,
                'day_of_week' => 0,
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            DB::table('driver_details')->whereIn('user_id', $chunk)->update(['same_time_for_every_day' => 1]);
        }
    }

    public function down(): void
    {
    }
};
