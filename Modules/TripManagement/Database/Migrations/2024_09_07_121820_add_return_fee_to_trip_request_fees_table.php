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
        Schema::table('trip_request_fees', function (Blueprint $table) {
            $table->decimal('return_fee', 23, 3)->default(0)->after('cancellation_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_request_fees', function (Blueprint $table) {
            $table->dropColumn('return_fee');
        });
    }
};
