<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->renameColumn('is_suspended', 'is_paused');
            $table->renameColumn('suspend_reason', 'pause_reason');
        });
    }

    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->renameColumn('is_paused', 'is_suspended');
            $table->renameColumn('pause_reason', 'suspend_reason');
        });
    }
};
