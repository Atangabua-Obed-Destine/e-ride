<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counter tills (E-Ride, bus books): each count of a counter agent's cash. The
 * cash an agent takes at the counter sits on 5712 tagged with the agent; a count
 * moves what was counted to where it was put (the safe, the bank...) and posts
 * any difference as cash short (658) or over (758). Never deleted: a count is
 * voided by reversing its entry.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('till_counts', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('agent_id', 36);
            $t->date('count_date');
            $t->decimal('expected', 22, 4)->default(0);
            $t->decimal('counted', 22, 4)->default(0);
            $t->decimal('difference', 22, 4)->default(0);
            $t->unsignedBigInteger('to_account_id')->nullable();
            $t->text('note')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->string('status', 20)->default('posted'); // posted | void
            $t->string('void_reason', 500)->nullable();
            $t->string('voided_by', 36)->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->string('counted_by', 36)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'agent_id']);
            $t->index(['business_id', 'count_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('till_counts');
    }
};
