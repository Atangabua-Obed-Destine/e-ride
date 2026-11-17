<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branches: a restaurant with several kitchens (Romz Mile 6, Romz Commercial Ave).
 * The main store is the brand: it owns the menu, photos and rating. A branch is a
 * store with parent_id = the main store: its own location, hours, orders and login.
 * store_item_states holds each kitchen's switches for a dish: sold out today
 * (back at midnight) or not made at this branch. It replaces store_items.sold_out_until,
 * which is copied over and no longer read.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('stores', 'parent_id')) {
            Schema::table('stores', function (Blueprint $t) {
                $t->foreignUuid('parent_id')->nullable()->index();
                $t->string('branch_name', 60)->nullable();
            });
        }
        if (!Schema::hasTable('store_item_states')) {
            Schema::create('store_item_states', function (Blueprint $t) {
                $t->id();
                $t->foreignUuid('store_id')->index();
                $t->foreignUuid('store_item_id')->index();
                $t->timestamp('sold_out_until')->nullable();
                $t->boolean('not_here')->default(false);
                $t->timestamps();
                $t->unique(['store_id', 'store_item_id']);
            });
            $now = now();
            DB::table('store_items')->whereNotNull('sold_out_until')->where('sold_out_until', '>', $now)
                ->orderBy('id')->get(['id', 'store_id', 'sold_out_until'])
                ->each(fn($i) => DB::table('store_item_states')->insert([
                    'store_id' => $i->store_id, 'store_item_id' => $i->id, 'sold_out_until' => $i->sold_out_until,
                    'not_here' => false, 'created_at' => $now, 'updated_at' => $now,
                ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_item_states');
        Schema::table('stores', function (Blueprint $t) {
            $t->dropColumn(['parent_id', 'branch_name']);
        });
    }
};
