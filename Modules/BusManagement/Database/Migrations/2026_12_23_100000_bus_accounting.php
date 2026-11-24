<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting for each bus agency branch (OHADA double entry, one set of books per branch).
 *
 *  - bus_money_moves: every time money moves on a booking (a ticket paid, a refund owed, a refund
 *    sent, a seat moved to another trip), written as it happens by the booking observer. Append
 *    only: one row per event, its own date, so history is never lost. The ledger posts from these.
 *  - bus_acc_books: a branch's books (start date, entry numbers, default accounts).
 *  - bus_acc_accounts / bus_acc_entries / bus_acc_lines: chart of accounts and the journal. Every
 *    line carries tags (trip, bus, route, sales channel, counter agent) for profit per bus or trip
 *    and the cash each agent holds.
 *  - bus_acc_documents: what people record by hand (expenses, supplier payments, other income,
 *    money moved between accounts, an agent's cash count, a statement check, journal entries).
 *  - bus_acc_months: closed months (nothing posts into them) and reopenings with their reason.
 *  - bus_acc_recognized: trips whose ticket money became sales (when the bus left).
 *  - bus_acc_cleared: bank and Mobile Money movements matched to a statement by a reconciliation.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bus_money_moves')) {
            Schema::create('bus_money_moves', function (Blueprint $t) {
                $t->id();
                $t->uuid('agency_id');
                $t->uuid('booking_id')->index();
                $t->uuid('departure_id')->nullable();
                $t->uuid('from_departure_id')->nullable();
                // sale | extra | refund_owed | cash_refund | refund_sent | rebook
                $t->string('kind', 12);
                // online | counter
                $t->string('channel', 8);
                // cash | momo | orange | fapshi | company
                $t->string('method', 8)->nullable();
                $t->decimal('amount', 12, 2);
                $t->uuid('staff_user_id')->nullable();
                $t->timestamp('at');
                $t->uuid('entry_id')->nullable();
                // Why it is not in the books: before_start (covered by the opening balances).
                $t->string('skipped', 16)->nullable();
                $t->boolean('backfilled')->default(false);
                $t->timestamp('created_at')->nullable();
                $t->index(['agency_id', 'entry_id', 'skipped']);
                $t->index(['agency_id', 'at']);
            });
        }

        if (!Schema::hasTable('bus_acc_books')) {
            Schema::create('bus_acc_books', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('agency_id')->unique();
                $t->date('starts_on');
                $t->unsignedInteger('next_number')->default(1);
                $t->unsignedInteger('next_document')->default(1);
                $t->json('settings')->nullable();
                $t->uuid('opened_by')->nullable();
                $t->timestamp('synced_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('bus_acc_accounts')) {
            Schema::create('bus_acc_accounts', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('agency_id');
                $t->string('code', 10);
                $t->string('name', 120);
                $t->unsignedTinyInteger('class');
                // asset | liability | equity | revenue | expense
                $t->string('type', 10);
                // A place money is kept (cash box, bank, Mobile Money): shown in "Money" and usable to pay.
                $t->boolean('money')->default(false);
                // The account's job for automatic postings (counter_cash, unearned, sales_counter, ...).
                $t->string('role', 20)->nullable();
                // Offered as a spending (or income) category.
                $t->boolean('category')->default(false);
                $t->boolean('active')->default(true);
                $t->timestamps();
                $t->unique(['agency_id', 'code']);
                $t->index(['agency_id', 'role']);
            });
        }

        if (!Schema::hasTable('bus_acc_entries')) {
            Schema::create('bus_acc_entries', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('agency_id');
                $t->string('number', 20);
                $t->date('date');
                // sales | cash | purchase | general | opening
                $t->string('journal', 10);
                $t->string('memo', 250);
                // move | departure | statement | statement_paid | document | opening
                $t->string('source_type', 16)->nullable();
                $t->string('source_id', 40)->nullable();
                // One posting per source event: a second try is refused by the database.
                $t->string('source_key', 80)->nullable();
                $t->decimal('total', 14, 2)->default(0);
                $t->uuid('reverses_id')->nullable();
                $t->uuid('reversed_by_id')->nullable();
                $t->uuid('created_by')->nullable();
                $t->timestamps();
                $t->unique(['agency_id', 'number']);
                $t->unique(['agency_id', 'source_key']);
                $t->index(['agency_id', 'date']);
            });
        }

        if (!Schema::hasTable('bus_acc_lines')) {
            Schema::create('bus_acc_lines', function (Blueprint $t) {
                $t->id();
                $t->uuid('entry_id')->index();
                $t->uuid('agency_id');
                $t->uuid('account_id');
                $t->date('date');
                $t->decimal('debit', 14, 2)->default(0);
                $t->decimal('credit', 14, 2)->default(0);
                $t->string('memo', 200)->nullable();
                $t->uuid('departure_id')->nullable();
                $t->uuid('vehicle_id')->nullable();
                $t->uuid('destination_id')->nullable();
                $t->string('channel', 8)->nullable();
                $t->uuid('staff_user_id')->nullable();
                $t->index(['agency_id', 'account_id', 'date']);
                $t->index(['agency_id', 'departure_id']);
                $t->index(['agency_id', 'vehicle_id']);
                $t->index(['agency_id', 'staff_user_id']);
            });
        }

        if (!Schema::hasTable('bus_acc_documents')) {
            Schema::create('bus_acc_documents', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('agency_id');
                $t->string('number', 20);
                // expense | bill_payment | income | transfer | cash_count | check | journal | opening
                $t->string('type', 14);
                $t->date('date');
                $t->decimal('amount', 14, 2)->default(0);
                // The category (expense or income account), or the account a check is for.
                $t->uuid('account_id')->nullable();
                // Paid from / received into / moved from.
                $t->uuid('money_account_id')->nullable();
                // Moved to (a transfer, or where an agent's counted cash goes).
                $t->uuid('to_account_id')->nullable();
                $t->uuid('parent_id')->nullable();
                $t->string('supplier', 120)->nullable();
                // An expense not paid yet (a supplier bill): what is still owed on it.
                $t->decimal('owed', 14, 2)->default(0);
                $t->date('due_on')->nullable();
                $t->uuid('vehicle_id')->nullable();
                $t->uuid('destination_id')->nullable();
                $t->uuid('departure_id')->nullable();
                $t->uuid('staff_user_id')->nullable();
                $t->decimal('expected', 14, 2)->nullable();
                $t->decimal('counted', 14, 2)->nullable();
                $t->decimal('fee', 14, 2)->default(0);
                $t->string('reference', 100)->nullable();
                $t->string('note', 500)->nullable();
                $t->date('reverse_on')->nullable();
                $t->json('lines')->nullable();
                // posted | void
                $t->string('status', 8)->default('posted');
                $t->uuid('entry_id')->nullable();
                $t->uuid('void_entry_id')->nullable();
                $t->uuid('auto_reverse_entry_id')->nullable();
                $t->string('void_reason', 300)->nullable();
                $t->uuid('created_by')->nullable();
                $t->uuid('voided_by')->nullable();
                $t->timestamp('voided_at')->nullable();
                $t->timestamps();
                $t->unique(['agency_id', 'number']);
                $t->index(['agency_id', 'type', 'date']);
            });
        }

        if (!Schema::hasTable('bus_acc_months')) {
            Schema::create('bus_acc_months', function (Blueprint $t) {
                $t->id();
                $t->uuid('agency_id');
                $t->date('month');
                $t->timestamp('closed_at')->nullable();
                $t->uuid('closed_by')->nullable();
                $t->timestamp('reopened_at')->nullable();
                $t->uuid('reopened_by')->nullable();
                $t->string('reopen_reason', 300)->nullable();
                $t->timestamps();
                $t->unique(['agency_id', 'month']);
            });
        }

        if (!Schema::hasTable('bus_acc_recognized')) {
            Schema::create('bus_acc_recognized', function (Blueprint $t) {
                $t->id();
                $t->uuid('agency_id')->index();
                $t->uuid('departure_id')->unique();
                $t->uuid('entry_id')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('bus_acc_cleared')) {
            // Movements of a bank or Mobile Money account matched to a statement by a reconciliation:
            // never offered again (unless that reconciliation is voided).
            Schema::create('bus_acc_cleared', function (Blueprint $t) {
                $t->id();
                $t->uuid('agency_id');
                $t->unsignedBigInteger('line_id')->unique();
                $t->uuid('document_id')->index();
                $t->timestamp('created_at')->nullable();
            });
        }

        $this->backfillMoves();
    }

    /**
     * The money that already moved before this release, rebuilt from the bookings as well as they
     * allow (one sale per paid booking, at its payment time; refunds at their cancel or sent time).
     * Only used when a branch opens its books on a past date.
     */
    protected function backfillMoves(): void
    {
        if (DB::table('bus_money_moves')->exists()) {
            return;
        }
        $companyMoney = DB::table('bus_companies')->where('money_mode', 'company')->pluck('id')->map(fn($id) => (string)$id)->all();
        DB::table('bus_bookings as b')
            ->join('bus_agencies as a', 'a.id', '=', 'b.agency_id')
            ->where(fn($q) => $q->whereNotNull('b.paid_at')->orWhere('b.extra_refund', '>', 0))
            ->select('b.*', 'a.company_id')
            ->orderBy('b.created_at')
            ->chunk(500, function ($rows) use ($companyMoney) {
                $moves = [];
                foreach ($rows as $b) {
                    $counter = $b->source === 'counter';
                    $channel = $counter ? 'counter' : 'online';
                    $online = $b->company_id && in_array((string)$b->company_id, $companyMoney, true) ? 'company' : 'fapshi';
                    $base = ['agency_id' => $b->agency_id, 'booking_id' => $b->id, 'departure_id' => $b->departure_id, 'channel' => $channel,
                        'backfilled' => true, 'created_at' => now(), 'from_departure_id' => null, 'staff_user_id' => null];
                    if ($b->paid_at) {
                        $moves[] = $base + ['kind' => 'sale', 'method' => $counter ? ($b->payment_method ?: 'cash') : $online,
                            'amount' => $b->amount, 'at' => $b->paid_at, 'staff_user_id' => $counter ? $b->staff_user_id : null];
                    }
                    if ((float)$b->extra_refund > 0) {
                        $moves[] = $base + ['kind' => 'extra', 'method' => $online, 'amount' => $b->extra_refund, 'at' => $b->updated_at ?? $b->created_at];
                    }
                    if ((float)$b->refund_amount > 0 && $b->paid_at) {
                        $cash = $counter && $b->cancelled_by && !in_array($b->cancelled_by, ['passenger', 'closure'], true);
                        $moves[] = $base + ['kind' => $cash ? 'cash_refund' : 'refund_owed', 'method' => $cash ? ($b->payment_method ?: 'cash') : null,
                            'amount' => $b->refund_amount, 'at' => $b->cancelled_at ?? $b->updated_at];
                    }
                    $sent = ((float)$b->refund_amount + (float)$b->extra_refund) - ($b->refund_status === 'due' ? (float)$b->refund_owed : 0);
                    $cashPart = $counter && $b->cancelled_by && !in_array($b->cancelled_by, ['passenger', 'closure'], true) ? (float)$b->refund_amount : 0;
                    $sent -= $cashPart;
                    if ($sent > 0.004 && $b->refund_sent_at) {
                        $moves[] = $base + ['kind' => 'refund_sent', 'method' => null, 'amount' => round($sent, 2), 'at' => $b->refund_sent_at];
                    }
                }
                if ($moves) {
                    DB::table('bus_money_moves')->insert($moves);
                }
            });
    }

    public function down(): void
    {
        foreach (['bus_acc_cleared', 'bus_acc_recognized', 'bus_acc_months', 'bus_acc_documents', 'bus_acc_lines', 'bus_acc_entries', 'bus_acc_accounts', 'bus_acc_books', 'bus_money_moves'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
