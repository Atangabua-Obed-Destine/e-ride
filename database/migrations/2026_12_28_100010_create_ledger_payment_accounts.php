<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * paxhitestbed's Payment Accounts (migrations 2025_10_09_2000*), per set of books:
 * the real containers of money (cash box, bank account, mobile-money wallet), each
 * linked to its class-5 ledger account, with a running balance kept from its
 * transactions: deposits, withdrawals, fund transfers and the incomes, expenses
 * and supplier payments that went through it.
 *
 * E-Ride: every table carries business_id (the books, App\Ledger\Ledger); user
 * columns are UUID strings; a manual deposit or withdrawal may name the other side
 * of its ledger entry (contra_account_id).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_account_types', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('title');
            $t->string('slug');
            $t->string('kind', 20)->default('cash'); // cash | bank | mobile_money
            $t->text('description')->nullable();
            $t->boolean('status')->default(1);
            $t->timestamps();
            $t->unique(['business_id', 'slug']);
        });

        Schema::create('payment_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('title');
            $t->string('account_number')->nullable();
            $t->unsignedBigInteger('account_type_id');
            $t->unsignedBigInteger('chart_of_account_id')->nullable();
            $t->decimal('opening_balance', 22, 4)->default(0);
            $t->decimal('current_balance', 22, 4)->default(0);
            $t->text('description')->nullable();
            $t->boolean('status')->default(1);
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'status']);
        });

        Schema::create('payment_account_transactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('payment_account_id');
            $t->string('transaction_type', 10); // credit (money in) | debit (money out)
            $t->decimal('amount', 22, 4);
            $t->date('transaction_date');
            $t->string('title')->nullable();
            $t->text('description')->nullable();
            $t->string('reference_type', 40)->nullable(); // income, expense, transfer, supplier_payment... or null (manual)
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('payment_reference')->nullable();
            $t->decimal('balance_after', 22, 4)->default(0);
            $t->unsignedBigInteger('contra_account_id')->nullable(); // E-Ride: the other side of a manual movement's entry
            $t->string('attach')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'payment_account_id', 'transaction_date'], 'pat_account_date');
            $t->index(['business_id', 'reference_type', 'reference_id'], 'pat_reference');
        });

        Schema::create('payment_account_transfers', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('from_account_id');
            $t->unsignedBigInteger('to_account_id');
            $t->decimal('amount', 22, 4);
            $t->date('transfer_date');
            $t->text('note')->nullable();
            $t->string('attach')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'transfer_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_account_transfers');
        Schema::dropIfExists('payment_account_transactions');
        Schema::dropIfExists('payment_accounts');
        Schema::dropIfExists('payment_account_types');
    }
};
