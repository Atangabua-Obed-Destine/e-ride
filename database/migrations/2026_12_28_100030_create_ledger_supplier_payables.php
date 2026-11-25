<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * paxhitestbed's supplier bills and payments (2026_11_24_100000_create_supplier_payables_tables),
 * per set of books, with the suppliers they are owed to (paxhitestbed's item_suppliers).
 * E-Ride: a bill may carry recoverable VAT (vat_amount, posted to the VAT recoverable mapping, 4452).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('item_suppliers', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('title');
            $t->string('contact_person')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('address')->nullable();
            $t->string('tax_number', 40)->nullable(); // NIU
            $t->unsignedSmallInteger('payment_terms_days')->default(0);
            $t->boolean('status')->default(1);
            $t->string('created_by', 36)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'title']);
        });

        Schema::create('supplier_bills', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('supplier_id');
            $t->string('kind', 20)->default('bill'); // bill | opening_balance
            $t->string('number', 30);
            $t->string('supplier_reference', 100)->nullable();
            $t->date('bill_date');
            $t->date('due_date');
            $t->string('description')->nullable();
            $t->decimal('vat_amount', 22, 4)->default(0);
            $t->decimal('total', 22, 4)->default(0);
            $t->string('status', 20)->default('draft'); // draft | approved | void
            $t->unsignedBigInteger('payable_account_id')->nullable();
            $t->string('attach')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->string('approved_by', 36)->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('void_journal_entry_id')->nullable();
            $t->string('voided_by', 36)->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->text('void_reason')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'number']);
            $t->index(['business_id', 'supplier_id', 'status']);
        });

        Schema::create('supplier_bill_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('supplier_bill_id');
            $t->unsignedSmallInteger('line_number')->default(1);
            $t->string('description')->nullable();
            $t->unsignedBigInteger('expense_category_id')->nullable();
            $t->unsignedBigInteger('account_id')->nullable();
            $t->decimal('amount', 22, 4);
            $t->timestamps();
            $t->index(['business_id', 'supplier_bill_id']);
        });

        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('supplier_id');
            $t->string('number', 30);
            $t->date('payment_date');
            $t->decimal('amount', 22, 4);
            $t->string('payment_method', 20)->nullable();
            $t->unsignedBigInteger('cash_account_id');
            $t->unsignedBigInteger('payment_account_id')->nullable();
            $t->unsignedBigInteger('payment_account_transaction_id')->nullable();
            $t->string('reference', 100)->nullable();
            $t->text('note')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->unsignedBigInteger('void_journal_entry_id')->nullable();
            $t->string('voided_by', 36)->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->text('void_reason')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'number']);
            $t->index(['business_id', 'supplier_id']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('supplier_payment_id');
            $t->unsignedBigInteger('supplier_bill_id');
            $t->decimal('amount', 22, 4);
            $t->timestamps();
            $t->index(['business_id', 'supplier_bill_id'], 'spa_bill');
            $t->index(['business_id', 'supplier_payment_id'], 'spa_payment');
        });
    }

    public function down()
    {
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_bill_lines');
        Schema::dropIfExists('supplier_bills');
        Schema::dropIfExists('item_suppliers');
    }
};
