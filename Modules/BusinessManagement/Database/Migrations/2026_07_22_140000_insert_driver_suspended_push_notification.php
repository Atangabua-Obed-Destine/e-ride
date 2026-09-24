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
                ['name' => 'driver_suspended', 'type' => 'others', 'group' => 'driver'],
                [
                    'value' => 'Your account has been suspended by admin. You cannot login until the suspension is removed.',
                    'dynamic_values' => json_encode(['{userName}', '{sentTime}']),
                    'status' => 1,
                    'action' => 'driver_suspended',
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
                ->where(['name' => 'driver_suspended', 'type' => 'others', 'group' => 'driver'])
                ->delete();
        }
    }
};
