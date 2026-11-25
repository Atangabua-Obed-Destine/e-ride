<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring entries (écritures récurrentes), paxhitestbed's tables with
 * business_id. Text columns instead of enums: paxhitestbed's frequency enum
 * listed bi_weekly / semi_annually while its code wrote biweekly /
 * semiannually. A notes column, which its pause / resume / skip wrote to.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('recurring_journal_entries')) {
            Schema::create('recurring_journal_entries', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->string('name');
                $t->text('description')->nullable();
                $t->string('journal_type', 30)->default('general');
                $t->string('frequency', 20)->default('monthly'); // daily weekly biweekly monthly quarterly semiannually annually
                $t->unsignedTinyInteger('day_of_month')->nullable();
                $t->unsignedTinyInteger('day_of_week')->nullable();
                $t->unsignedTinyInteger('month_of_year')->nullable();
                $t->date('start_date');
                $t->date('end_date')->nullable();
                $t->date('next_run_date');
                $t->date('last_run_date')->nullable();
                $t->unsignedInteger('occurrences')->nullable();
                $t->unsignedInteger('occurrences_completed')->default(0);
                $t->boolean('is_active')->default(true);
                $t->boolean('auto_post')->default(false);
                $t->decimal('total_debit', 22, 4)->default(0);
                $t->decimal('total_credit', 22, 4)->default(0);
                $t->text('notes')->nullable();
                $t->string('created_by', 36)->nullable();
                $t->string('updated_by', 36)->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['business_id', 'is_active', 'next_run_date'], 'rje_due');
            });
        }

        if (! Schema::hasTable('recurring_journal_entry_lines')) {
            Schema::create('recurring_journal_entry_lines', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->unsignedBigInteger('recurring_journal_entry_id');
                $t->unsignedBigInteger('account_id');
                $t->unsignedInteger('line_number')->default(1);
                $t->text('description')->nullable();
                $t->decimal('debit', 22, 4)->default(0);
                $t->decimal('credit', 22, 4)->default(0);
                $t->timestamps();

                $t->index(['business_id', 'recurring_journal_entry_id'], 'rje_lines_rje');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('recurring_journal_entry_lines');
        Schema::dropIfExists('recurring_journal_entries');
    }
};
