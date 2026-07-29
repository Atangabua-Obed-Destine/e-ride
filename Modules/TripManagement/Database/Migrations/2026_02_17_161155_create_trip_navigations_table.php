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
        Schema::create('trip_navigations', function (Blueprint $table) {
            $table->uuid('id')->primary()->index();
            $table->foreignUuid('trip_request_id')->unique()->constrained();
            $table->text('encoded_polyline');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_navigations');
    }
};
