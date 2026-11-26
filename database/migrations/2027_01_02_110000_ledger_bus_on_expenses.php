<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride: an expense, or a line of a supplier bill, may name the bus it was for (fuel,
 * tyres, repairs). Its cost line then carries the bus, and Profit by bus counts it.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['expenses', 'supplier_bill_lines'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'vehicle_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->uuid('vehicle_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['expenses', 'supplier_bill_lines'] as $table) {
            if (Schema::hasColumn($table, 'vehicle_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('vehicle_id'));
            }
        }
    }
};
