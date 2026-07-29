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
        Schema::table('driver_details', function (Blueprint $table) {
            $table->integer('ride_count')->default(0)->after('service');
            $table->integer('parcel_count')->default(0)->after('ride_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropColumn(['ride_count', 'parcel_count']);
        });
    }
};
