<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The items a partner chose for their Status picture, in their order (store item ids or Market item codes). */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('share_looks') && !Schema::hasColumn('share_looks', 'items')) {
            Schema::table('share_looks', function (Blueprint $table) {
                $table->json('items')->nullable()->after('item');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('share_looks', 'items')) {
            Schema::table('share_looks', fn(Blueprint $table) => $table->dropColumn('items'));
        }
    }
};
