<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('firebase_push_notifications')) {
            DB::table('firebase_push_notifications')->updateOrInsert(
                ['name' => 'driver_status_paused', 'type' => 'others', 'group' => 'driver'],
                [
                    'value' => 'Your status has been paused for {pauseDuration} by admin. You cannot go online until the pause ends.',
                    'dynamic_values' => json_encode(['{pauseDuration}']),
                    'status' => 1,
                    'action' => 'driver_status_paused',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('firebase_push_notifications')) {
            DB::table('firebase_push_notifications')
                ->where(['name' => 'driver_status_paused', 'type' => 'others', 'group' => 'driver'])
                ->delete();
        }
    }
};
