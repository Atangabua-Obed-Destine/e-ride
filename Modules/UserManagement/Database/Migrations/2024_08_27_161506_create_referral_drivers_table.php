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
        Schema::create('referral_drivers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id');
            $table->foreignUuid('ref_by');
            $table->double('ref_by_earning_amount')->default(0);
            $table->double('driver_earning_amount')->default(0);
            $table->boolean('is_used')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_drivers');
    }
};
