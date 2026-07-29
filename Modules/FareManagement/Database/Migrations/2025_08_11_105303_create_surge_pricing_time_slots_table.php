<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('surge_pricing_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('surge_pricing_id');
            $table->string('start_date');
            $table->string('end_date');
            $table->json('selected_days')->nullable();
            $table->json('slots');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surge_pricing_time_slots');
    }
};
