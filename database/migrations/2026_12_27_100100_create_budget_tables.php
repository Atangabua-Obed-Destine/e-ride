<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Budgets (paxhitestbed's budget module): the Income & Expenditure sheet, its
 * lines, departmental budgets with their allocations, revisions, and the
 * quantity x amount working behind a figure. Every table carries business_id.
 *
 * Differences from paxhitestbed, on purpose:
 *  - a budget line targets a ledger account (and optionally an expense category
 *    and a location) instead of being reached through category mappings; its
 *    actual is read from the posted ledger;
 *  - "department" is a business location (location_id), there are no faculties;
 *  - committed_amount is not created: paxhitestbed never wrote it (always 0);
 *  - no student_count on the working: tuition forecasting is school-only.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('budget_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('code', 20);
            $t->string('name', 191);
            $t->string('name_fr', 191)->nullable();
            $t->string('description', 500)->nullable();
            $t->string('section', 20);                         // income expenditure capital
            $t->boolean('is_header')->default(false);
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->unsignedBigInteger('account_id')->nullable();   // the ledger account (and its sub-accounts) it reads
            $t->unsignedInteger('expense_category_id')->nullable();
            $t->unsignedInteger('location_id')->nullable();
            $t->boolean('is_local')->default(true);             // false: made from the chart of accounts
            $t->integer('sort_order')->default(0);
            $t->boolean('status')->default(true);               // false: retired, off the sheet
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'code']);
            $t->index(['business_id', 'section', 'sort_order']);
            $t->index('parent_id');
            $t->index('account_id');
        });

        Schema::create('budgets', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('title', 191);
            $t->string('budget_code', 30);
            $t->string('type', 20)->default('annual');          // annual departmental project
            $t->unsignedInteger('location_id')->nullable();     // the department
            $t->unsignedBigInteger('parent_id')->nullable();    // the annual sheet it is delegated from
            $t->string('fiscal_year', 10)->nullable();
            $t->date('start_date');
            $t->date('end_date');
            $t->decimal('total_amount', 22, 4)->default(0);
            $t->decimal('opening_balance', 22, 4)->default(0);
            $t->boolean('is_institutional')->default(false);    // the Income & Expenditure sheet
            $t->decimal('allocated_amount', 22, 4)->default(0);
            $t->decimal('spent_amount', 22, 4)->default(0);
            $t->decimal('remaining_amount', 22, 4)->default(0);
            $t->string('status', 20)->default('draft');         // draft pending_approval approved active closed cancelled
            $t->text('description')->nullable();
            $t->text('note')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->string('approved_by', 36)->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'budget_code']);
            $t->index(['business_id', 'is_institutional', 'start_date']);
            $t->index(['business_id', 'status']);
            $t->index('parent_id');
        });

        Schema::create('budget_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('budget_id');
            $t->unsignedBigInteger('budget_line_id')->nullable();    // a figure on the sheet
            $t->unsignedInteger('expense_category_id')->nullable(); // a departmental allocation
            $t->unsignedBigInteger('account_id')->nullable();       // a departmental allocation
            $t->unsignedInteger('location_id')->nullable();
            $t->string('title', 191)->nullable();
            $t->decimal('allocated_amount', 22, 4)->default(0);
            $t->decimal('spent_amount', 22, 4)->default(0);
            $t->decimal('remaining_amount', 22, 4)->default(0);
            $t->string('period', 20)->default('yearly');             // yearly q1 q2 q3 q4 semester1 semester2
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');
            $t->index(['business_id', 'budget_id']);
            $t->index('budget_line_id');
        });

        Schema::create('budget_revisions', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('budget_id');
            $t->integer('revision_number')->default(1);
            $t->decimal('previous_amount', 22, 4)->default(0);
            $t->decimal('new_amount', 22, 4)->default(0);
            $t->decimal('change_amount', 22, 4)->default(0);
            $t->string('change_type', 20)->nullable();               // increase decrease reallocation
            $t->text('reason')->nullable();
            $t->text('justification')->nullable();
            $t->string('status', 20)->default('pending');            // pending approved rejected
            $t->string('requested_by', 36)->nullable();
            $t->string('approved_by', 36)->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->text('rejected_reason')->nullable();
            $t->timestamps();
            $t->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');
            $t->index(['business_id', 'budget_id']);
        });

        Schema::create('budget_line_forecasts', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('budget_id');
            $t->unsignedBigInteger('budget_line_id');
            $t->decimal('quantity', 22, 4)->default(0);
            $t->decimal('rate', 22, 4)->default(0);
            $t->string('rate_basis', 20)->default('unit');
            $t->decimal('computed_amount', 22, 4)->default(0);
            $t->string('note', 500)->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');
            $t->unique(['budget_id', 'budget_line_id']);
            $t->index('business_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('budget_line_forecasts');
        Schema::dropIfExists('budget_revisions');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('budget_lines');
    }
};
