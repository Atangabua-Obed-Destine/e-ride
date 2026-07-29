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
            'name' => 'parcel_return_penalty',
            'value' => 'You have been penalized {approximateAmount} for not returning the parcel ID: {parcelId} in due time.',
            'dynamic_values' => json_encode(['{approximateAmount}', '{parcelId}']),
            'status' => 1,
            'type' => 'parcel',
            'group' => 'driver',
            'action'=> 'parcel_return_penalty',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('firebase_push_notifications')->where(['name' => 'parcel_return_penalty', 'group' => 'driver', 'type' => 'parcel'])->delete();
    }
};
