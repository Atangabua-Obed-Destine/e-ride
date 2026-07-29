<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('firebase_push_notifications', function (Blueprint $table) {
            DB::table('firebase_push_notifications')->insert([
                ['name' => 'schedule_trip_booked', 'value' => 'Schedule trip booked.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'trip_booked', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'schedule_trip_edited', 'value' => 'Schedule trip edited.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'trip_edited', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'schedule_trip_accepted_by_driver', 'value' => 'Schedule trip accepted by driver.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'trip_accepted', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'driver_on_the_way_to_pickup_location', 'value' => 'Driver on the way to pickup location.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'driver_on_the_way', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'schedule_ride_started', 'value' => 'Schedule ride started.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'trip_started', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'schedule_ride_completed', 'value' => 'Schedule ride completed.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'trip_completed', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'schedule_ride_canceled', 'value' => 'Schedule ride canceled.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'trip_canceled', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'schedule_ride_paused',  'value' => 'Schedule ride paused.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'trip_paused', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'schedule_ride_resumed', 'value' => 'Schedule ride resumed.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'trip_resumed', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'driver_canceled_schedule_trip_request', 'value' => 'Driver canceled the schedule trip request.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'driver_canceled_ride_request', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'payment_successful', 'value' => '{paidAmount} payment successful on this trip by {methodName}.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'customer', 'action' => 'payment_successful', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'new_schedule_trip_request', 'value' => 'New schedule trip request.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'driver', 'action' => 'new_ride_request', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'pickup_time_started', 'value' => 'Pickup time started.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'driver', 'action' => 'pickup_time_started', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'tips_from_customer', 'value' => 'Customer has given the tips {tipsAmount} with payment.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'driver', 'action' => 'tips_from_customer', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'customer_canceled_the_trip', 'value' => 'Customer canceled the trip.', 'status' => 1, 'type' => 'schedule_trip', 'group' => 'driver', 'action' => 'customer_canceled_trip', 'created_at' => now(), 'updated_at' => now()],
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('firebase_push_notifications', function (Blueprint $table) {
            $notifications = [
                'schedule_trip_booked',
                'schedule_trip_edited',
                'schedule_trip_accepted_by_driver',
                'driver_on_the_way_to_pickup_location',
                'schedule_ride_started',
                'schedule_ride_completed',
                'schedule_ride_canceled',
                'schedule_ride_paused',
                'schedule_ride_resumed',
                'driver_canceled_schedule_trip_request',
                'payment_successful',
                'new_schedule_trip_request',
                'pickup_time_started',
                'tips_from_customer',
                'customer_canceled_the_trip',
            ];

            DB::table('firebase_push_notifications')->where('type', 'schedule_trip')->whereIn('name', $notifications)->delete();
        });
    }
};
