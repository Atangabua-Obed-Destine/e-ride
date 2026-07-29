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
            'name' => 'parcel_canceled_after_trip_started',
            'value' => 'Parcel canceled. Your paid amount is returned to your wallet',
            'dynamic_values' => json_encode(['{parcelId}', '{approximateAmount}']),
            'status' => 1,
            'type' => 'parcel',
            'group' => 'customer',
            'action'=> 'parcel_canceled_after_trip_started',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('firebase_push_notifications')->where(['name' => 'parcel_canceled_after_trip_started', 'group' => 'customer', 'type' => 'parcel'])->delete();
    }
};
