<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Head office books, buses as assets, and year-end closing.
 *
 *  - bus_acc_books.kind: "branch" (agency_id is a bus agency) or "company" (agency_id is a bus company:
 *    the head office's own books). Every accounting table keys its rows by that books id.
 *  - bus_acc_accounts.link_id: on a head office's books, the branch a "branch account" (liaison,
 *    OHADA 18) stands for.
 *  - bus_acc_assets: buses carried as fixed assets (value, start, life, end value), worn down monthly.
 *  - bus_acc_years: closed financial years (the closing entry moves the result to 1301).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('bus_acc_books', 'kind')) {
            Schema::table('bus_acc_books', function (Blueprint $t) {
                $t->string('kind', 8)->default('branch')->after('agency_id');
            });
        }
        if (!Schema::hasColumn('bus_acc_accounts', 'link_id')) {
            Schema::table('bus_acc_accounts', function (Blueprint $t) {
                $t->uuid('link_id')->nullable()->after('role');
            });
        }
        if (!Schema::hasTable('bus_acc_assets')) {
            Schema::create('bus_acc_assets', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('agency_id')->index();
                $t->uuid('vehicle_id')->index();
                $t->string('name', 120);
                $t->decimal('value', 14, 2);
                $t->decimal('end_value', 14, 2)->default(0);
                $t->unsignedSmallInteger('life_months');
                $t->date('starts_on');
                // How it came into the books: owner (brought in by the owner), paid, owed (bill or loan).
                $t->string('funded_by', 8);
                // active | sold | retired
                $t->string('status', 8)->default('active');
                $t->uuid('document_id')->nullable();
                $t->date('disposed_on')->nullable();
                $t->decimal('sale_price', 14, 2)->nullable();
                $t->uuid('disposal_document_id')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('bus_acc_years')) {
            Schema::create('bus_acc_years', function (Blueprint $t) {
                $t->id();
                $t->uuid('agency_id');
                $t->unsignedSmallInteger('year');
                $t->timestamp('closed_at')->nullable();
                $t->uuid('closed_by')->nullable();
                $t->uuid('entry_id')->nullable();
                $t->decimal('result', 14, 2)->default(0);
                $t->timestamp('reopened_at')->nullable();
                $t->uuid('reopened_by')->nullable();
                $t->string('reopen_reason', 300)->nullable();
                $t->timestamps();
                $t->unique(['agency_id', 'year']);
            });
        }
        // The time a money move happened: a plain DATETIME. As the first TIMESTAMP of its table, some
        // MySQL/MariaDB settings made it "update to now on every change" (it moved when the move posted).
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE bus_money_moves MODIFY `at` DATETIME NOT NULL');
        }
        // Branches that already have books get the new accounts (buses, result of the year, loans).
        if (!class_exists(\Modules\BusManagement\Entities\BusAccBook::class)) {
            return; // the first accounting build is gone
        }
        foreach (\Modules\BusManagement\Entities\BusAccBook::where('kind', 'branch')->get() as $books) {
            $agency = \Modules\BusManagement\Entities\BusAgency::find($books->agency_id);
            if ($agency) {
                app(\Modules\BusManagement\Service\Accounting\Ledger::class)->seedChart($agency);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_acc_years');
        Schema::dropIfExists('bus_acc_assets');
        if (Schema::hasColumn('bus_acc_accounts', 'link_id')) {
            Schema::table('bus_acc_accounts', fn(Blueprint $t) => $t->dropColumn('link_id'));
        }
        if (Schema::hasColumn('bus_acc_books', 'kind')) {
            Schema::table('bus_acc_books', fn(Blueprint $t) => $t->dropColumn('kind'));
        }
    }
};
