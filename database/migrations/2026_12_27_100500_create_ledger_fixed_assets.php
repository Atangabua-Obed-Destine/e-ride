<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed assets in the ledger (paxhitestbed fixed_asset_categories, fixed_assets,
 * depreciation_schedules), for many businesses in one database: every table
 * carries business_id, codes are unique per business, text columns instead of
 * enums.
 *
 * Not to be confused with the AssetManagement module (tables assets,
 * asset_transactions, asset_maintenances, asset_warranties): that one is an
 * equipment register with allocations and no ledger postings; this one is the
 * accounting register whose figures post to classes 2, 28 and 68.
 *
 * depreciation_schedules has the columns paxhitestbed's model and service use
 * (status, *_before / *_after); paxhitestbed's table was created with another
 * set and had to be patched (2026_11_24_280000 there). One row per asset per
 * month (unique), so a month cannot be charged twice and a reversed month is
 * charged again on the same row.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('fixed_asset_categories', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('code', 20)->nullable();
            $t->string('name');
            $t->string('name_fr')->nullable();
            $t->text('description')->nullable();
            $t->string('depreciation_method', 30)->default('straight_line'); // straight_line declining_balance sum_of_years units_of_production none
            $t->unsignedInteger('useful_life_years')->default(5);
            $t->decimal('salvage_value_percent', 5, 2)->default(0);
            $t->unsignedBigInteger('asset_account_id')->nullable();                    // 2x
            $t->unsignedBigInteger('depreciation_account_id')->nullable();             // 68x, the charge
            $t->unsignedBigInteger('accumulated_depreciation_account_id')->nullable(); // 28x
            $t->unsignedBigInteger('disposal_account_id')->nullable();                 // gain / loss on disposal (else 82x / 81x)
            $t->boolean('is_active')->default(true);
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'is_active']);
        });

        Schema::create('fixed_assets', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('asset_code', 40);
            $t->string('name');
            $t->string('name_fr')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('category_id');
            $t->string('serial_number')->nullable();
            $t->string('model')->nullable();
            $t->string('manufacturer')->nullable();
            $t->string('location')->nullable();
            $t->string('vendor')->nullable();
            $t->unsignedInteger('custodian_id')->nullable();
            $t->date('acquisition_date');
            $t->decimal('acquisition_cost', 22, 4);
            $t->decimal('salvage_value', 22, 4)->default(0);
            $t->unsignedInteger('useful_life_months');
            $t->string('depreciation_method', 30)->default('straight_line');
            $t->decimal('declining_balance_rate', 6, 2)->nullable();
            $t->date('depreciation_start_date');
            $t->decimal('accumulated_depreciation', 22, 4)->default(0);
            $t->decimal('book_value', 22, 4);
            $t->date('last_depreciation_date')->nullable();
            $t->string('status', 30)->default('active'); // active fully_depreciated disposed under_maintenance lost
            $t->date('disposal_date')->nullable();
            $t->decimal('disposal_value', 22, 4)->nullable();
            $t->string('disposal_method', 30)->nullable();
            $t->string('disposal_reason', 500)->nullable();
            $t->unsignedBigInteger('disposal_journal_entry_id')->nullable();
            $t->date('warranty_expiry')->nullable();
            $t->decimal('insurance_value', 22, 4)->nullable();
            $t->string('insurance_policy_number')->nullable();
            $t->text('notes')->nullable();
            $t->string('image_path')->nullable();
            $t->unsignedBigInteger('purchase_journal_entry_id')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['business_id', 'asset_code']);
            $t->index(['business_id', 'status']);
            $t->index('category_id');
            $t->foreign('category_id')->references('id')->on('fixed_asset_categories');
        });

        Schema::create('depreciation_schedules', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('fixed_asset_id');
            $t->unsignedBigInteger('fiscal_year_id')->nullable();
            $t->unsignedBigInteger('accounting_period_id')->nullable();
            $t->date('depreciation_date'); // the month end
            $t->unsignedInteger('period_number')->nullable();
            $t->decimal('depreciation_amount', 22, 4);
            $t->decimal('accumulated_depreciation_before', 22, 4)->default(0);
            $t->decimal('accumulated_depreciation_after', 22, 4)->default(0);
            $t->decimal('book_value_before', 22, 4)->default(0);
            $t->decimal('book_value_after', 22, 4)->default(0);
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->string('status', 20)->default('pending'); // pending posted reversed
            $t->string('posted_by', 36)->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->text('notes')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->unique(['fixed_asset_id', 'depreciation_date'], 'depreciation_asset_month_unique');
            $t->index(['business_id', 'status']);
            $t->index(['business_id', 'fiscal_year_id']);
            $t->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('depreciation_schedules');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
    }
};
