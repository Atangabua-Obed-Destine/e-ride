<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['user_type', 'is_active'], 'users_type_active_index');
        });

        Schema::table('user_last_locations', function (Blueprint $table) {
            $table->index(['zone_id', 'user_id'], 'ull_zone_user_index');
            $table->index(['user_id', 'zone_id'], 'ull_user_zone_index');
        });

        Schema::table('driver_details', function (Blueprint $table) {
            $table->index(['user_id', 'is_online'], 'dd_user_online_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_type_active_index');
        });

        Schema::table('user_last_locations', function (Blueprint $table) {
            $table->dropIndex('ull_zone_user_index');
            $table->dropIndex('ull_user_zone_index');
        });

        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropIndex('dd_user_online_index');
        });
    }
};
