<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parcel_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary()->index();
            $table->string('readable_id')->nullable();
            $table->foreignUuid('trip_request_id');
            $table->foreignUuid('coupon_setup_id')->nullable();
            $table->decimal('parcel_approximate_price', 23, 6)->default(0);
            $table->decimal('refund_amount_by_admin', 23, 6)->default(0);
            $table->string('reason')->nullable();
            $table->string('approval_note')->nullable();
            $table->string('deny_note')->nullable();
            $table->string('note')->nullable();
            $table->string('refund_method')->nullable();
            $table->string('status', 20)->default('pending');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcel_refunds');
    }
};
