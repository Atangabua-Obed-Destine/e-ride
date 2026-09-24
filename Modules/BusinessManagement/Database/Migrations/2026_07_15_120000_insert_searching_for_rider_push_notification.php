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
                ['name' => 'searching_for_rider', 'type' => 'others', 'group' => 'customer'],
                [
                    'value' => 'Your ride is canceled. Looking for the best rider for you. Please wait.',
                    'dynamic_values' => null,
                    'status' => 1,
                    'action' => 'searching_for_rider',
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
                ->where(['name' => 'searching_for_rider', 'type' => 'others', 'group' => 'customer'])
                ->delete();
        }
    }
};
