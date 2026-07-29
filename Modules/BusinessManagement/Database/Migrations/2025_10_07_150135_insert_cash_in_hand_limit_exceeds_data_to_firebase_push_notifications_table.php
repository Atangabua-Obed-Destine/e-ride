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
            'name' => 'cash_in_hand_limit_exceeds',
            'value' => 'Your limit to hold cash in hand has been exceeded. Please pay the due to admin from your wallet page.',
            'dynamic_values' => json_encode(['{driverName}']),
            'status' => 1,
            'type' => 'others',
            'group' => 'cash_in_hand',
            'action'=> 'cash_in_hand_limit_exceeds',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('firebase_push_notifications')->where(['name' => 'cash_in_hand_limit_exceeds', 'group' => 'cash_in_hand', 'type' => 'others'])->delete();
    }
};
