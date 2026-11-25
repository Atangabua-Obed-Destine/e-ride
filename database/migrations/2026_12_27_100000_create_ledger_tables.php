<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The paxhitestbed ledger, as ported to PosInnova (one training for accountants
 * across every system). In E-Ride a "business" is one set of books (a bus
 * branch, or a bus company's head office: ledger_businesses), so every table
 * carries business_id and codes / entry numbers are unique per set of books.
 * E-Ride users have UUIDs: every *_by column (and contact_id) is a string.
 *
 * Text columns instead of enums (paxhitestbed's journal_type enum silently
 * stored '' for a value it did not list).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('chart_of_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('account_code', 20);
            $t->string('account_name');
            $t->string('account_name_fr')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->tinyInteger('class_number');
            $t->string('account_type', 20);              // asset liability equity revenue expense other
            $t->string('account_category', 20)->default('detail'); // detail (can post) heading total subtotal
            $t->string('cash_flow_category', 20)->nullable();       // operating investing financing cash none
            $t->string('cash_flow_subcategory')->nullable();
            $t->decimal('opening_balance', 22, 4)->default(0);
            $t->decimal('current_balance', 22, 4)->default(0);
            $t->string('normal_balance', 6);               // debit credit
            $t->boolean('is_active')->default(true);
            $t->boolean('is_system')->default(false);
            $t->integer('display_order')->default(0);
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['business_id', 'account_code']);
            $t->index(['business_id', 'class_number']);
            $t->index('parent_id');
        });

        Schema::create('fiscal_years', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('name');
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('is_active')->default(false);
            $t->boolean('is_closed')->default(false);
            $t->string('closed_by', 36)->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->text('closing_note')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'start_date', 'end_date']);
        });

        Schema::create('accounting_periods', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('fiscal_year_id');
            $t->string('name');
            $t->string('french_name')->nullable();
            $t->integer('period_number');
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('is_closed')->default(false);
            $t->string('closed_by', 36)->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->text('closing_note')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->onDelete('cascade');
            $t->unique(['fiscal_year_id', 'period_number']);
            $t->index(['business_id', 'start_date', 'end_date']);
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('entry_number', 30);
            $t->date('entry_date');
            $t->unsignedBigInteger('fiscal_year_id')->nullable();
            $t->unsignedBigInteger('accounting_period_id')->nullable();
            $t->string('journal_type', 30)->default('general');
            $t->string('description', 255)->nullable();
            $t->string('reference_number')->nullable();
            $t->string('reference_type', 60)->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->decimal('total_debit', 22, 4)->default(0);
            $t->decimal('total_credit', 22, 4)->default(0);
            $t->boolean('is_posted')->default(false);
            $t->boolean('is_system_generated')->default(false);
            $t->string('posted_by', 36)->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->boolean('is_reversed')->default(false);
            $t->unsignedBigInteger('reversed_entry_id')->nullable(); // on a reversal: the entry it reverses
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['business_id', 'entry_number']);
            $t->index(['business_id', 'entry_date']);
            $t->index(['business_id', 'is_posted']);
            $t->index(['reference_type', 'reference_id']);
            $t->index('reversed_entry_id');
        });

        Schema::create('journal_entry_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('journal_entry_id');
            $t->unsignedBigInteger('account_id');
            $t->integer('line_number');
            $t->text('description')->nullable();
            $t->decimal('debit', 22, 4)->default(0);
            $t->decimal('credit', 22, 4)->default(0);
            $t->string('contact_id', 36)->nullable(); // the customer / supplier of a 411 / 401 line
            $t->timestamps();
            $t->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
            $t->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $t->index(['journal_entry_id', 'line_number']);
            $t->index(['business_id', 'account_id']);
            $t->index('contact_id');
        });

        // Which ledger account each kind of document / category / payment method uses.
        Schema::create('default_account_mappings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('mapping_type', 40);        // e.g. sale_goods, vat_collected, expense_category, payment_method
            $t->string('category_key', 60)->default(''); // expense category id, payment method, payment account id ...
            $t->unsignedBigInteger('debit_account_id')->nullable();
            $t->unsignedBigInteger('credit_account_id')->nullable();
            $t->text('description')->nullable();
            $t->boolean('status')->default(1);
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'mapping_type', 'category_key'], 'dam_business_type_key');
        });

        // A POS document (sale, purchase, payment, expense...) and the entry it posted.
        Schema::create('transaction_mappings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('transaction_type', 30);    // sell sell_return purchase purchase_return expense opening_balance payment
            $t->string('transaction_id', 36);
            $t->unsignedBigInteger('debit_account_id')->nullable();
            $t->unsignedBigInteger('credit_account_id')->nullable();
            $t->decimal('amount', 22, 4)->default(0);
            $t->date('transaction_date')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->string('fingerprint', 64)->nullable(); // hash of the lines it posted: a change means re-post
            $t->dateTime('mapped_at')->nullable();
            $t->string('mapped_by', 36)->nullable();
            $t->string('status', 20)->default('posted'); // posted failed none
            $t->text('error')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'transaction_type', 'transaction_id'], 'tm_business_type_id');
            $t->index('journal_entry_id');
            $t->index(['business_id', 'status']);
        });

        Schema::create('accounting_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('setting_key');
            $t->text('setting_value')->nullable();
            $t->string('setting_type')->default('string');
            $t->string('category')->default('general');
            $t->text('description')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'setting_key']);
        });
    }

    public function down()
    {
        foreach (['accounting_settings', 'transaction_mappings', 'default_account_mappings', 'journal_entry_lines',
            'journal_entries', 'accounting_periods', 'fiscal_years', 'chart_of_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
