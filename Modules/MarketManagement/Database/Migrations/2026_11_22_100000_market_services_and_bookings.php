<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride Market services: an item can be a service (booked for a time, or quoted), shops say
 * when they are free, and bookings run from request to a code at the end, like orders.
 * E-Ride never holds the money: the client pays the provider after the job.
 *
 * Every new timestamp column is nullable: on MariaDB the first NOT NULL timestamp of a table
 * gets ON UPDATE CURRENT_TIMESTAMP and would change by itself on every update.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_items', function (Blueprint $table) {
            // fixed | from | hour | quote (services only)
            $table->string('price_type', 10)->nullable()->after('sale_type');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('price_type');
            // ["place","client","online"]
            $table->json('service_where')->nullable()->after('duration_minutes');
            // [{"name":"Long","price":2000}]
            $table->json('extras')->nullable()->after('service_where');
            $table->string('service_kind', 20)->nullable()->after('extras');
        });

        Schema::table('market_shops', function (Blueprint $table) {
            // {"mon":["08:00","18:00"], ..., "sun":null}
            $table->json('service_hours')->nullable()->after('min_order');
            $table->unsignedTinyInteger('service_capacity')->default(1)->after('service_hours');
            $table->unsignedSmallInteger('service_notice_minutes')->default(120)->after('service_capacity');
            $table->unsignedSmallInteger('service_ahead_days')->default(14)->after('service_notice_minutes');
            $table->boolean('service_auto_confirm')->default(false)->after('service_ahead_days');
            $table->boolean('taking_bookings')->default(true)->after('service_auto_confirm');
            $table->string('service_areas', 300)->nullable()->after('taking_bookings');
            $table->decimal('travel_fee', 14, 2)->nullable()->after('service_areas');
            $table->unsignedInteger('bookings_completed')->default(0)->after('orders_completed');
        });

        Schema::create('market_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 12)->unique();
            // booking (a time from the free slots) | quote (a price and a time the provider proposes)
            $table->string('kind', 10)->default('booking');
            $table->string('status', 20)->index();
            $table->foreignUuid('item_id')->index();
            $table->foreignUuid('shop_id')->index();
            $table->foreignUuid('client_id')->index();
            $table->foreignUuid('provider_id')->index();
            $table->foreignUuid('conversation_id')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('suggested_starts_at')->nullable();
            // place (the provider's) | client (the client's place) | online
            $table->string('location', 10)->default('place');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('note', 600)->nullable();
            $table->json('photos')->nullable();
            $table->string('when_text', 80)->nullable();
            $table->json('extras')->nullable();
            $table->string('price_type', 10)->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->decimal('quote_price', 14, 2)->nullable();
            $table->string('quote_note', 400)->nullable();
            $table->timestamp('quote_expires_at')->nullable();
            $table->decimal('final_price', 14, 2)->nullable();
            $table->string('receive_code', 4)->nullable();
            $table->timestamp('respond_by')->nullable()->index();
            $table->string('decline_reason', 300)->nullable();
            $table->string('cancelled_by', 10)->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->boolean('late_cancel')->default(false);
            $table->boolean('reminded_day')->default(false);
            $table->boolean('reminded_hour')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'starts_at']);
        });

        Schema::table('market_reviews', function (Blueprint $table) {
            $table->foreignUuid('booking_id')->nullable()->after('order_id')->index();
            $table->foreignUuid('order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('market_reviews', function (Blueprint $table) {
            $table->dropColumn('booking_id');
        });
        Schema::dropIfExists('market_bookings');
        Schema::table('market_shops', function (Blueprint $table) {
            $table->dropColumn(['service_hours', 'service_capacity', 'service_notice_minutes', 'service_ahead_days', 'service_auto_confirm',
                'taking_bookings', 'service_areas', 'travel_fee', 'bookings_completed']);
        });
        Schema::table('market_items', function (Blueprint $table) {
            $table->dropColumn(['price_type', 'duration_minutes', 'service_where', 'extras', 'service_kind']);
        });
    }
};
