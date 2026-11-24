<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bus companies: a head office (Vatican Express) over its branches (each bus agency is one branch, one city).
 * The company chooses where the money goes (each branch's Fapshi, or one company account) and how E-Ride
 * bills it (a statement per branch, or one for the company). Buses belong to the company, so any branch
 * can use them.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bus_companies')) {
            Schema::create('bus_companies', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('name', 120);
                $t->string('slug', 140)->unique();
                $t->string('logo')->nullable();
                $t->text('about')->nullable();
                $t->string('status', 12)->default('active');
                // Where passengers' money goes: each branch's own Fapshi, or one company account.
                $t->string('money_mode', 12)->default('branch');
                $t->text('fapshi')->nullable();
                $t->timestamp('fapshi_ok_at')->nullable();
                $t->string('webhook_code', 24)->unique();
                // How E-Ride bills: a statement per branch, or one for the company.
                $t->string('bill_mode', 12)->default('branch');
                $t->json('rules_template')->nullable();
                // Time a bus needs after arriving before it can leave again.
                $t->unsignedSmallInteger('turnaround_minutes')->default(60);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('bus_company_staff')) {
            Schema::create('bus_company_staff', function (Blueprint $t) {
                $t->id();
                $t->uuid('company_id')->index();
                $t->uuid('user_id')->unique();
                $t->string('role', 12); // director | regional | accountant
                $t->json('agency_ids')->nullable(); // the branches of a regional manager
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('bus_company_statements')) {
            Schema::create('bus_company_statements', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('company_id');
                $t->date('month');
                $t->decimal('total', 14, 2)->default(0);
                $t->date('due_on')->nullable();
                $t->string('status', 10)->default('due');
                $t->timestamp('paid_at')->nullable();
                $t->string('reference', 120)->nullable();
                $t->uuid('marked_by')->nullable();
                $t->timestamps();
                $t->unique(['company_id', 'month']);
            });
        }

        Schema::table('bus_agencies', function (Blueprint $t) {
            if (!Schema::hasColumn('bus_agencies', 'company_id')) {
                $t->uuid('company_id')->nullable()->index()->after('id');
                $t->string('branch_label', 80)->nullable()->after('company_id');
            }
            if (!Schema::hasColumn('bus_agencies', 'region')) {
                $t->string('region', 30)->nullable()->after('origin_city');
            }
        });
        Schema::table('bus_vehicles', function (Blueprint $t) {
            if (!Schema::hasColumn('bus_vehicles', 'company_id')) {
                $t->uuid('company_id')->nullable()->index()->after('agency_id');
            }
        });
        Schema::table('bus_statements', function (Blueprint $t) {
            if (!Schema::hasColumn('bus_statements', 'company_statement_id')) {
                $t->uuid('company_statement_id')->nullable()->index()->after('agency_id');
            }
        });
        Schema::table('bus_departures', function (Blueprint $t) {
            // Finding a bus's other departures quickly (one bus, one departure at a time).
            $t->index(['vehicle_id', 'date'], 'bus_departures_vehicle_date');
        });

        // Regions for the agencies already there.
        foreach (\Illuminate\Support\Facades\DB::table('bus_agencies')->whereNull('region')->get(['id', 'origin_city']) as $a) {
            $region = \Modules\BusManagement\Lib\CameroonRegions::of($a->origin_city);
            if ($region) {
                \Illuminate\Support\Facades\DB::table('bus_agencies')->where('id', $a->id)->update(['region' => $region]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bus_departures', fn(Blueprint $t) => $t->dropIndex('bus_departures_vehicle_date'));
        Schema::table('bus_statements', fn(Blueprint $t) => $t->dropColumn('company_statement_id'));
        Schema::table('bus_vehicles', fn(Blueprint $t) => $t->dropColumn('company_id'));
        Schema::table('bus_agencies', fn(Blueprint $t) => $t->dropColumn(['company_id', 'branch_label', 'region']));
        Schema::dropIfExists('bus_company_statements');
        Schema::dropIfExists('bus_company_staff');
        Schema::dropIfExists('bus_companies');
    }
};
