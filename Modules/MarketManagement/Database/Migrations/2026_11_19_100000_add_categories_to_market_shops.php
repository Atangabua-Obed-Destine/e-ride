<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A shop can sell several kinds of things: a list of categories (the old single one becomes the first). */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_shops', function (Blueprint $table) {
            $table->json('categories')->nullable()->after('category');
        });
        DB::table('market_shops')->whereNotNull('category')->orderBy('id')->each(function ($shop) {
            DB::table('market_shops')->where('id', $shop->id)->update(['categories' => json_encode([$shop->category])]);
        });
    }

    public function down(): void
    {
        Schema::table('market_shops', function (Blueprint $table) {
            $table->dropColumn('categories');
        });
    }
};
