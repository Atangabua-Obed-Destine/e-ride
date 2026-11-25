<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sets of books of the ledger (App\Ledger). posinnova keeps one set of books
 * per business in its `business` table; in E-Ride a set of books belongs to a bus
 * branch (bus_agencies) or to a bus company's head office (bus_companies). Every
 * ledger table's business_id points here.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('ledger_businesses', function (Blueprint $t) {
            $t->increments('id');
            $t->string('owner_type', 20);            // bus_agency | bus_company
            $t->string('owner_id', 36);
            $t->string('name');
            $t->json('enabled_modules')->nullable(); // ["accounting"] once the books are set up (posinnova's switch)
            $t->json('custom_labels')->nullable();
            $t->timestamps();
            $t->unique(['owner_type', 'owner_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ledger_businesses');
    }
};
