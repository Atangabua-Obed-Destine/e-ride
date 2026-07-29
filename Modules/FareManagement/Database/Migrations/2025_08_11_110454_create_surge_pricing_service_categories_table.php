<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surge_pricing_service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('surge_pricing_id');
            $table->string('service_category_type', 100);
            $table->uuid('service_category_id');
            $table->double('surge_multiplier');
            $table->timestamps();
            $table->index(['service_category_type', 'service_category_id'], 'sp_scat_scid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surge_pricing_service_categories');
    }
};
