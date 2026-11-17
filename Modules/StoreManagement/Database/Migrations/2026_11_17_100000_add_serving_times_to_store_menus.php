<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily menus: when a dish or a whole section is served (days of the week and
 * an optional time window), and "sold out today", which comes back by itself.
 * served_days is a bitmask, Monday = 1 ... Sunday = 64; NULL means every day.
 * is_available on items now means "on the menu" (off = hidden from customers).
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['store_items', 'store_menu_categories'] as $table) {
            if (!Schema::hasColumn($table, 'served_days')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedTinyInteger('served_days')->nullable();
                    $t->string('served_from', 5)->nullable();
                    $t->string('served_until', 5)->nullable();
                });
            }
        }
        if (!Schema::hasColumn('store_items', 'sold_out_until')) {
            Schema::table('store_items', function (Blueprint $t) {
                $t->timestamp('sold_out_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('store_items', function (Blueprint $t) {
            $t->dropColumn(['served_days', 'served_from', 'served_until', 'sold_out_until']);
        });
        Schema::table('store_menu_categories', function (Blueprint $t) {
            $t->dropColumn(['served_days', 'served_from', 'served_until']);
        });
    }
};
