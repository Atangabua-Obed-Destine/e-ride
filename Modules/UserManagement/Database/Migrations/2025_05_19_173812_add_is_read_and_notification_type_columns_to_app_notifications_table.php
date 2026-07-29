<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->boolean('is_read')->default(1)->after('action');
            $table->string('notification_type')->after('type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropColumn('is_read');
            $table->dropColumn('notification_type');
        });
    }
};
