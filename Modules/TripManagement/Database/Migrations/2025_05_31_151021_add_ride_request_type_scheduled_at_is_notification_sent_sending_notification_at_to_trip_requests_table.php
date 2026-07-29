<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->string('ride_request_type')->after('type')->nullable();
            $table->string('scheduled_at')->after('ride_request_type')->nullable();
            $table->boolean('is_notification_sent')->default(1)->after('current_status');
            $table->string('sending_notification_at')->after('is_notification_sent')->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropColumn('ride_request_type');
            $table->dropColumn('scheduled_at');
            $table->dropColumn('is_notification_sent');
            $table->dropColumn('sending_notification_at');
        });
    }
};
