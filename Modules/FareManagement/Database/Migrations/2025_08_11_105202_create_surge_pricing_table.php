<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surge_pricing', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('readable_id')->unique();
            $table->string('name');
            $table->string('surge_pricing_for');
            $table->boolean('increase_for_all_vehicles')->default(1);
            $table->double('all_vehicle_surge_percent')->nullable();
            $table->boolean('increase_for_all_parcels')->default(1);
            $table->double('all_parcel_surge_percent')->nullable();
            $table->string('zone_setup_type')->nullable();
            $table->string('schedule');
            $table->boolean('is_active')->default(1);
            $table->string('customer_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surge_pricing');
    }
};
