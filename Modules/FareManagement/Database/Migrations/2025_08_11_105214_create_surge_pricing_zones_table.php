<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surge_pricing_zones', function (Blueprint $table) {
            $table->foreignUuid('surge_pricing_id');
            $table->foreignUuid('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surge_pricing_zones');
    }
};
