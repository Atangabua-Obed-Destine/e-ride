<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->index(['driver_id', 'type', 'current_status'], 'tr_driver_type_status_index');
            $table->index(['customer_id', 'type', 'current_status'], 'tr_customer_type_status_index');
        });

        Schema::table('safety_alerts', function (Blueprint $table) {
            $table->index(['sent_by', 'status'], 'sa_sentby_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex('tr_driver_type_status_index');
            $table->dropIndex('tr_customer_type_status_index');
        });

        Schema::table('safety_alerts', function (Blueprint $table) {
            $table->dropIndex('sa_sentby_status_index');
        });
    }
};
