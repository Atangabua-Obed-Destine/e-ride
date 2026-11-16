<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The booker's ID on a booking (only the person booking gives one), and tickets collected and printed at
 * the agency counter.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bus_bookings', function (Blueprint $table) {
            // id_card | passport
            $table->string('booker_id_type', 10)->nullable()->after('phone');
            $table->string('booker_id_number', 40)->nullable()->after('booker_id_type');
            $table->uuid('booker_verification_id')->nullable()->after('booker_id_number');
            // E-Ride ID Verified when booked.
            $table->boolean('booker_verified')->default(false)->after('booker_verification_id');
            $table->uuid('id_checked_by')->nullable()->after('booker_verified');
            $table->timestamp('id_checked_at')->nullable()->after('id_checked_by');
            $table->timestamp('collect_reminded_at')->nullable()->after('id_checked_at');
        });
        Schema::table('bus_tickets', function (Blueprint $table) {
            $table->timestamp('collected_at')->nullable()->after('boarded_by');
            $table->uuid('collected_by')->nullable()->after('collected_at');
            $table->unsignedSmallInteger('print_count')->default(0)->after('collected_by');
            $table->timestamp('last_printed_at')->nullable()->after('print_count');
        });
    }

    public function down(): void
    {
        Schema::table('bus_tickets', function (Blueprint $table) {
            $table->dropColumn(['collected_at', 'collected_by', 'print_count', 'last_printed_at']);
        });
        Schema::table('bus_bookings', function (Blueprint $table) {
            $table->dropColumn(['booker_id_type', 'booker_id_number', 'booker_verification_id', 'booker_verified', 'id_checked_by', 'id_checked_at', 'collect_reminded_at']);
        });
    }
};
