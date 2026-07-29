<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('firebase_push_notifications')->insert([
            'name' => 'face_verification_completed_successfully',
            'value' => 'You are now a verified driver - start earning more with {businessName}',
            'dynamic_values' => json_encode(['{businessName}']),
            'status' => 1,
            'type' => 'others',
            'group' => 'face_verification',
            'action'=> 'face_verification_completed_successfully',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('firebase_push_notifications')->where(['name' => 'face_verification_completed_successfully', 'group' => 'face_verification', 'type' => 'others'])->delete();
    }
};
