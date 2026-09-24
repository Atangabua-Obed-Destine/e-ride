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
                ['name' => 'trip_canceled_for_driver_identity_mismatch', 'type' => 'others', 'group' => 'driver'],
                [
                    'value' => 'Your trip has been canceled because you did not match {reason}.',
                    'dynamic_values' => json_encode(['{reason}']),
                    'status' => 1,
                    'action' => 'trip_canceled_for_driver_identity_mismatch',
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
                ->where(['name' => 'trip_canceled_for_driver_identity_mismatch', 'type' => 'others', 'group' => 'driver'])
                ->delete();
        }
    }
};
