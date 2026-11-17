<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The restaurant confirms before the customer pays. An order is sent
 * (awaiting_confirmation), the restaurant confirms it, suggests changes the
 * customer accepts or refuses (changes_proposed, with the proposal), or
 * declines; once confirmed the customer pays by pay_by, and the kitchen starts.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('store_orders', 'confirmed_at')) {
            Schema::table('store_orders', function (Blueprint $t) {
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('pay_by')->nullable();
                $t->json('proposal')->nullable();
                $t->timestamp('proposed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $t) {
            $t->dropColumn(['confirmed_at', 'pay_by', 'proposal', 'proposed_at']);
        });
    }
};
