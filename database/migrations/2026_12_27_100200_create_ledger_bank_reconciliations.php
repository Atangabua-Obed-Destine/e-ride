<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank reconciliation (rapprochement bancaire), paxhitestbed's tables as its
 * BankReconciliationService uses them (by ledger account, not payment
 * account), with business_id and text columns instead of enums.
 *
 * An item remembers the ledger line it lists (journal_entry_line_id), so a
 * movement ticked on one statement is never offered again (paxhitestbed
 * matched on entry + direction + amount).
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('bank_reconciliations')) {
            Schema::create('bank_reconciliations', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->unsignedBigInteger('chart_of_account_id');
                $t->date('statement_date');
                $t->date('reconciliation_date');
                $t->string('statement_reference', 190)->nullable();
                $t->decimal('statement_opening_balance', 22, 4)->default(0);
                $t->decimal('statement_closing_balance', 22, 4)->default(0);
                $t->decimal('book_balance', 22, 4)->default(0);
                $t->decimal('adjusted_book_balance', 22, 4)->nullable();
                $t->decimal('outstanding_deposits', 22, 4)->default(0);
                $t->decimal('outstanding_checks', 22, 4)->default(0);
                $t->decimal('bank_charges', 22, 4)->default(0);
                $t->decimal('bank_interest', 22, 4)->default(0);
                $t->decimal('other_adjustments', 22, 4)->default(0);
                $t->decimal('difference', 22, 4)->default(0);
                $t->string('status', 20)->default('in_progress'); // draft in_progress completed approved
                $t->text('notes')->nullable();
                $t->string('reconciled_by', 36)->nullable();
                $t->timestamp('reconciled_at')->nullable();
                $t->string('approved_by', 36)->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->string('created_by', 36)->nullable();
                $t->string('updated_by', 36)->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['business_id', 'chart_of_account_id', 'statement_date'], 'bank_rec_account_date');
                $t->index(['business_id', 'status']);
            });
        }

        if (! Schema::hasTable('bank_reconciliation_items')) {
            Schema::create('bank_reconciliation_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->unsignedBigInteger('bank_reconciliation_id');
                $t->string('item_type', 30); // deposit_in_transit outstanding_check bank_charge bank_interest
                $t->unsignedBigInteger('account_id')->nullable(); // charge / interest: the class 6 / 7 account
                $t->string('reference_number', 191)->nullable();
                $t->date('transaction_date');
                $t->string('description', 190)->nullable();
                $t->decimal('amount', 22, 4)->default(0);
                $t->string('effect', 10); // add subtract
                $t->boolean('is_cleared')->default(false);
                $t->date('cleared_date')->nullable();
                $t->unsignedBigInteger('journal_entry_id')->nullable();
                $t->unsignedBigInteger('journal_entry_line_id')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();

                $t->index(['business_id', 'bank_reconciliation_id'], 'bank_rec_items_rec');
                $t->index(['business_id', 'journal_entry_line_id'], 'bank_rec_items_line');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('bank_reconciliation_items');
        Schema::dropIfExists('bank_reconciliations');
    }
};
