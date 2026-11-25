<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * paxhitestbed's Income & Expense (migrations 2021_06_05_*, payment_account_id
 * 2025_10_09_2100*), per set of books. Its school budget columns on expenses
 * (budget_id, budget_allocation_id, approval) are left out.
 *
 * The categories keep the shape Mapping settings already reads (name, parent_id,
 * deleted_at); paxhitestbed calls the name "title".
 */
return new class extends Migration
{
    public function up()
    {
        foreach (['income_categories', 'expense_categories'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->string('name');
                $t->string('slug');
                $t->unsignedBigInteger('parent_id')->nullable();
                $t->text('description')->nullable();
                $t->boolean('status')->default(1);
                $t->string('created_by', 36)->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['business_id', 'status']);
            });
        }

        foreach (['incomes', 'expenses'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->unsignedBigInteger('category_id');
                $t->string('title');
                $t->string('invoice_id')->nullable();
                $t->decimal('amount', 22, 4);
                $t->date('date');
                $t->string('reference')->nullable();
                $t->string('payment_method', 20)->nullable();
                $t->unsignedBigInteger('payment_account_id')->nullable();
                $t->text('note')->nullable();
                $t->string('attach')->nullable();
                $t->boolean('status')->default(1);
                $t->string('created_by', 36)->nullable();
                $t->string('updated_by', 36)->nullable();
                $t->timestamps();
                $t->index(['business_id', 'date']);
                $t->index(['business_id', 'category_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('income_categories');
    }
};
