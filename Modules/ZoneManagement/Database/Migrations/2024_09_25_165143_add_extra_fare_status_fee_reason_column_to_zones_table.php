<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->boolean('extra_fare_status')->default(0)->after('is_active');
            $table->double('extra_fare_fee')->default(0)->after('extra_fare_status');
            $table->string('extra_fare_reason')->nullable()->after('extra_fare_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['extra_fare_status', 'extra_fare_fee', 'extra_fare_reason']);
        });
    }
};
