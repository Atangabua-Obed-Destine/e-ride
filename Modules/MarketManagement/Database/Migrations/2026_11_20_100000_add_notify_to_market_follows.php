<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A follower chooses whether to be told when the shop posts (on by default, as before). */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_follows', function (Blueprint $table) {
            $table->boolean('notify')->default(true)->after('shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('market_follows', function (Blueprint $table) {
            $table->dropColumn('notify');
        });
    }
};
