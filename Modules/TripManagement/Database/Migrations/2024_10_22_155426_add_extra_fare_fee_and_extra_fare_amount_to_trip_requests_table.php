<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->decimal('extra_fare_fee', 23, 3)->after('cancellation_fee')->default(0);
            $table->decimal('extra_fare_amount', 23, 3)->after('extra_fare_fee')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropColumn(['extra_fare_fee', 'extra_fare_amount']);
        });
    }
};
