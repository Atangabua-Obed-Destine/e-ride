<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->unsignedInteger('pause_duration')->nullable()->after('pause_reason');
            $table->dateTime('paused_until')->nullable()->after('pause_duration');
        });
    }

    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropColumn(['pause_duration', 'paused_until']);
        });
    }
};
