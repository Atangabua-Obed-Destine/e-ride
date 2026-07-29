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
            'name' => 'parcel_canceled',
            'value' => 'Parcel canceled. Please return the parcel within {dueTime}.',
            'dynamic_values' => json_encode(['{parcelId}', '{sentTime}', '{dueTime}']),
            'status' => 1,
            'type' => 'parcel',
            'group' => 'driver',
            'action'=> 'parcel_canceled',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('firebase_push_notifications')->where(['name' => 'parcel_canceled', 'group' => 'driver', 'type' => 'parcel'])->delete();
    }
};
