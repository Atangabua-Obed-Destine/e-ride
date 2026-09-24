<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'social_refresh_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('social_refresh_token')->nullable()->after('logged_in_via');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'social_refresh_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('social_refresh_token');
            });
        }
    }
};
