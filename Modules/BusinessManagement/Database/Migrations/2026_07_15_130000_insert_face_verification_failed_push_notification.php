<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('firebase_push_notifications')->updateOrInsert(
            ['name' => 'face_verification_failed', 'type' => 'others', 'group' => 'face_verification'],
            [
                'value' => 'Your face verification has failed and your status has been paused. Please verify your identity again to go online.',
                'dynamic_values' => null,
                'status' => 1,
                'action' => 'face_verification_failed',
            ]
        );
    }

    public function down(): void
    {
        DB::table('firebase_push_notifications')
            ->where(['name' => 'face_verification_failed', 'type' => 'others', 'group' => 'face_verification'])
            ->delete();
    }
};
