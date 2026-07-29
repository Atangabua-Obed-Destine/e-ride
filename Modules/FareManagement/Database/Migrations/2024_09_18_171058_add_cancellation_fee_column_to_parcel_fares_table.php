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
        Schema::table('parcel_fares', function (Blueprint $table) {
            $table->double('cancellation_fee')->default(0)->after('return_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcel_fares', function (Blueprint $table) {
            $table->dropColumn('cancellation_fee');
        });
    }
};
