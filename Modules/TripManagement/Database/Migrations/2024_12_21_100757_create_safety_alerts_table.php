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
        Schema::create('safety_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary()->index();
            $table->foreignUuid('trip_request_id');
            $table->foreignUuid('sent_by');
            $table->json('reason')->nullable();
            $table->text('comment')->nullable();
            $table->text('alert_location');
            $table->text('resolved_location')->nullable();
            $table->integer('number_of_alert')->default(1);
            $table->foreignUuid('resolved_by')->nullable();
            $table->string('trip_status_when_make_alert');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_alerts');
    }
};
