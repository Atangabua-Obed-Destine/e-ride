<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Year-end closing (clôture de l'exercice), paxhitestbed's table with its
 * later columns (checklist, closing_journal_entry_id, approval) and
 * business_id. Status as text (paxhitestbed changed its enum to a string).
 *
 * No unique index on the fiscal year: paxhitestbed allows a new closing once
 * the previous one is reversed, which its unique index then refused.
 *
 * PosInnova keeps stock the SYSCOHADA intermittent way (purchases to 6011,
 * 311 moves only at year end), so the closing also carries the stock
 * variation entry and the stock values it was built from.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('year_end_closings')) {
            return;
        }
        Schema::create('year_end_closings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('fiscal_year_id');
            $t->string('status', 30)->default('draft'); // draft in_progress pending_approval completed reversed
            $t->date('closing_date');
            $t->string('closing_reference', 60)->nullable();
            $t->decimal('total_revenue', 22, 4)->default(0);
            $t->decimal('total_expenses', 22, 4)->default(0);
            $t->decimal('net_income', 22, 4)->default(0);
            $t->unsignedBigInteger('retained_earnings_account_id')->nullable();
            $t->unsignedBigInteger('income_summary_account_id')->nullable();
            $t->unsignedBigInteger('closing_journal_entry_id')->nullable();
            $t->unsignedBigInteger('stock_journal_entry_id')->nullable();
            $t->decimal('opening_stock_value', 22, 4)->nullable();
            $t->decimal('closing_stock_value', 22, 4)->nullable();
            $t->decimal('stock_valuation', 22, 4)->nullable();
            $t->string('stock_entered_by', 36)->nullable();
            $t->timestamp('stock_entered_at')->nullable();
            $t->text('checklist')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('approved_by', 36)->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->string('reversed_by', 36)->nullable();
            $t->timestamp('reversed_at')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();

            $t->index(['business_id', 'fiscal_year_id']);
            $t->index(['business_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('year_end_closings');
    }
};
