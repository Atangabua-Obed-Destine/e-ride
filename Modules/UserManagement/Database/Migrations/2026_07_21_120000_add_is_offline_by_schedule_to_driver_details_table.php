<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->boolean('is_offline_by_schedule')->default(0)->after('same_time_for_every_day');
        });
    }

    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropColumn('is_offline_by_schedule');
        });
    }
};
