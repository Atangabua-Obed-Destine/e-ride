<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App passengers are reminded of their bus twice: the evening before
 * (eve_reminded_at) and two hours before it leaves (reminded_at, which
 * website bookings already use for their WhatsApp reminder).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('bus_bookings', 'eve_reminded_at')) {
            Schema::table('bus_bookings', function (Blueprint $table) {
                $table->timestamp('eve_reminded_at')->nullable()->after('reminded_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bus_bookings', 'eve_reminded_at')) {
            Schema::table('bus_bookings', fn(Blueprint $table) => $table->dropColumn('eve_reminded_at'));
        }
    }
};
