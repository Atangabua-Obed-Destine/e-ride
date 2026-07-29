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
        Schema::create('referral_earning_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key_name', 191);
            $table->json('value');
            $table->string('settings_type', 191);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_earning_settings');
    }
};
