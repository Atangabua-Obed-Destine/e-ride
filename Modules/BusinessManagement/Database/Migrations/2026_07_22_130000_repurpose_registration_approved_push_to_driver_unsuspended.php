<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('firebase_push_notifications')) {
            $updated = DB::table('firebase_push_notifications')
                ->where('name', 'registration_approved')
                ->update([
                    'name' => 'driver_unsuspended',
                    'value' => 'Your account suspension has been removed by admin. You can login now.',
                    'dynamic_values' => json_encode(['{userName}', '{sentTime}']),
                    'type' => 'others',
                    'group' => 'driver',
                    'action' => 'driver_unsuspended',
                    'updated_at' => now(),
                ]);

            if (!$updated) {
                DB::table('firebase_push_notifications')->updateOrInsert(
                    ['name' => 'driver_unsuspended', 'type' => 'others', 'group' => 'driver'],
                    [
                        'value' => 'Your account suspension has been removed by admin. You can login now.',
                        'dynamic_values' => json_encode(['{userName}', '{sentTime}']),
                        'status' => 1,
                        'action' => 'driver_unsuspended',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
    }
};
