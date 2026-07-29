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
        Schema::create('late_return_penalty_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('trip_request_id');
            $table->timestamp('sending_notification_at');
            $table->boolean('is_notification_sent')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('late_return_penalty_notifications');
    }
};
