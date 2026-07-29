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
        Schema::create('referral_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id');
            $table->foreignUuid('ref_by');
            $table->double('ref_by_earning_amount')->default(0);
            $table->double('customer_discount_amount')->default(0);
            $table->string('customer_discount_amount_type')->nullable();
            $table->integer('customer_discount_validity')->default(0);
            $table->string('customer_discount_validity_type')->nullable();
            $table->boolean('is_used')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_customers');
    }
};
