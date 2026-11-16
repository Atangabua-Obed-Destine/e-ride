<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride Bus Travel: verified bus agencies sell seats in the app and at their counter, from one
 * partner portal. One departure city per agency, many destinations (drop-off points only).
 * A passenger asks for seats, the agency accepts, the passenger pays the agency through the
 * agency's own Fapshi account. E-Ride bills a % commission every month. Departures end at
 * "departed": nothing is tracked on the road.
 *
 * Every new timestamp column is nullable: on MariaDB the first NOT NULL timestamp of a table
 * gets ON UPDATE CURRENT_TIMESTAMP and would change by itself on every update.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bus_agencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('google_place_id', 191)->nullable()->unique();
            // The one city every departure leaves from (the office's city).
            $table->string('origin_city', 80);
            $table->string('address', 191)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('logo')->nullable();
            $table->string('about', 600)->nullable();
            // checking (E-Ride is verifying) | setup (portal open, not bookable yet) | live | paused
            $table->string('status', 12)->default('checking');
            // statement_late | declines | manual
            $table->string('pause_reason', 30)->nullable();
            $table->string('pause_note', 300)->nullable();
            // The verification checklist: licence, office, manager_id (who and when).
            $table->json('checks')->nullable();
            $table->string('licence_file')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            // E-Ride's commission, % of the fare, for tickets sold in the app and at the counter.
            $table->decimal('commission_app_pct', 5, 2)->default(0);
            $table->decimal('commission_counter_pct', 5, 2)->default(0);
            // The agency's own Fapshi account (api_user, api_key, webhook_secret, mode), encrypted.
            $table->text('fapshi')->nullable();
            $table->timestamp('fapshi_ok_at')->nullable();
            // The agency's own webhook address: /bus/fapshi/{webhook_code}.
            $table->string('webhook_code', 24)->unique();
            // Bags, cancellation tiers, seats kept for the counter, online cut-off.
            $table->json('rules')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('answer_seconds_avg')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['status', 'origin_city']);
        });

        Schema::create('bus_staff', function (Blueprint $table) {
            $table->id();
            $table->uuid('agency_id')->index();
            $table->uuid('user_id')->unique();
            // manager | counter | boarding
            $table->string('role', 10);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('bus_vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id')->index();
            $table->string('registration', 20);
            $table->string('name', 60)->nullable();
            // classic | vip | executive
            $table->string('class', 10)->default('classic');
            // ready | garage | retired
            $table->string('status', 10)->default('ready');
            // The seat plan, as on trivagocm's bus form.
            $table->unsignedSmallInteger('total_seats');
            // separate | front_left | front_right
            $table->string('driver_position', 12)->default('separate');
            $table->boolean('is_double_decker')->default(false);
            $table->unsignedSmallInteger('upper_deck_seats')->default(0);
            $table->boolean('has_rear_seats')->default(true);
            $table->unsignedTinyInteger('rear_seats_count')->default(5);
            $table->unsignedTinyInteger('seats_per_row')->default(4);
            $table->unsignedTinyInteger('aisle_position')->default(2);
            // front_row | second_row
            $table->string('first_seat_position', 12)->default('front_row');
            // [{row, side: left|right}]
            $table->json('door_positions')->nullable();
            $table->unsignedInteger('cargo_kg')->default(500);
            $table->json('amenities')->nullable();
            $table->json('photos')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['agency_id', 'registration']);
        });

        Schema::create('bus_destinations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id')->index();
            $table->string('city', 80);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['agency_id', 'city']);
        });

        Schema::create('bus_drop_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('destination_id')->index();
            $table->string('name', 120);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // Fares from the agency's city to here, per class (null: that class does not stop here).
            $table->decimal('fare_classic', 12, 2)->nullable();
            $table->decimal('fare_vip', 12, 2)->nullable();
            $table->decimal('fare_executive', 12, 2)->nullable();
            $table->unsignedSmallInteger('minutes_from_start')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // The weekly timetable: each line opens departures some days ahead.
        Schema::create('bus_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id')->index();
            $table->uuid('destination_id')->index();
            $table->string('class', 10)->default('classic');
            // fixed (leaves at the time) | when_full (from the time, leaves when full)
            $table->string('kind', 10)->default('fixed');
            $table->time('time');
            // Mon..Sun as bits 1..64
            $table->unsignedTinyInteger('days')->default(127);
            $table->uuid('vehicle_id')->nullable();
            // Free seats on its departures are confirmed at once.
            $table->boolean('auto_accept')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('bus_departures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id');
            $table->uuid('schedule_id')->nullable()->index();
            $table->uuid('destination_id')->index();
            $table->string('class', 10)->default('classic');
            $table->string('kind', 10)->default('fixed');
            $table->date('date');
            $table->time('time');
            $table->uuid('vehicle_id')->nullable();
            // The seat plan when it opened (or when its bus changed before any sale).
            $table->json('seat_plan')->nullable();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->boolean('auto_accept')->default(false);
            // scheduled | boarding | departed | cancelled
            $table->string('status', 10)->default('scheduled');
            $table->time('delayed_to')->nullable();
            $table->timestamp('boarding_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['agency_id', 'date']);
            $table->index(['date', 'status']);
            $table->unique(['schedule_id', 'date']);
        });

        Schema::create('bus_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 12)->unique();
            $table->uuid('agency_id')->index();
            $table->uuid('departure_id')->index();
            // The passenger who booked in the app (null at the counter).
            $table->uuid('customer_id')->nullable()->index();
            // app | counter
            $table->string('source', 8);
            $table->uuid('staff_user_id')->nullable();
            $table->uuid('drop_point_id')->nullable();
            // requested | accepted | paid | declined | expired | cancelled
            $table->string('status', 10);
            $table->timestamp('respond_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('pay_by')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('commission_pct', 5, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            // momo | orange (through Fapshi) | cash (counter)
            $table->string('payment_method', 10)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('decline_reason', 200)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // passenger | agency | departure
            $table->string('cancelled_by', 10)->nullable();
            $table->decimal('refund_amount', 12, 2)->default(0);
            // none | due | sent
            $table->string('refund_status', 6)->default('none');
            $table->timestamp('refund_due_by')->nullable();
            $table->string('refund_ref', 100)->nullable();
            $table->timestamp('refund_sent_at')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['status', 'respond_by']);
            $table->index(['status', 'pay_by']);
        });

        // One per seat.
        Schema::create('bus_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 12)->unique();
            $table->uuid('booking_id')->index();
            $table->uuid('departure_id')->index();
            $table->string('seat', 6);
            $table->string('passenger_name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('id_number', 40)->nullable();
            $table->unsignedTinyInteger('bags')->default(1);
            $table->decimal('fare', 12, 2);
            // held (requested or accepted, not paid) | valid | boarded | no_show | cancelled
            $table->string('status', 10);
            $table->timestamp('boarded_at')->nullable();
            $table->uuid('boarded_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // A seat belongs to one live ticket at a time: the app, the counter and boarding cannot sell it twice.
        Schema::create('bus_seat_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('departure_id');
            $table->string('seat', 6);
            $table->uuid('ticket_id')->unique();
            $table->timestamp('created_at')->nullable();
            $table->unique(['departure_id', 'seat']);
        });

        // Payments through the agency's own Fapshi account.
        Schema::create('bus_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('booking_id')->index();
            $table->uuid('agency_id')->index();
            $table->string('environment', 10);
            $table->string('external_id', 100)->unique();
            $table->string('trans_id', 100)->nullable()->unique();
            $table->unsignedInteger('amount');
            // created | pending | successful | failed | expired | mismatched
            $table->string('status', 12)->default('created');
            $table->string('medium', 30)->nullable();
            $table->text('payment_link')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('bus_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id')->index();
            // The first day of the month it covers.
            $table->date('month');
            $table->unsignedInteger('app_tickets')->default(0);
            $table->decimal('app_fares', 14, 2)->default(0);
            $table->decimal('app_commission', 14, 2)->default(0);
            $table->unsignedInteger('counter_tickets')->default(0);
            $table->decimal('counter_fares', 14, 2)->default(0);
            $table->decimal('counter_commission', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->date('due_on');
            // due | paid
            $table->string('status', 6)->default('due');
            $table->timestamp('paid_at')->nullable();
            $table->string('reference', 100)->nullable();
            $table->uuid('marked_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['agency_id', 'month']);
        });

        // "Tell me when a seat frees up" on a full departure.
        Schema::create('bus_waitlist', function (Blueprint $table) {
            $table->id();
            $table->uuid('departure_id');
            $table->uuid('user_id');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['departure_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['bus_waitlist', 'bus_statements', 'bus_payments', 'bus_seat_claims', 'bus_tickets', 'bus_bookings', 'bus_departures',
                     'bus_schedules', 'bus_drop_points', 'bus_destinations', 'bus_vehicles', 'bus_staff', 'bus_agencies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
