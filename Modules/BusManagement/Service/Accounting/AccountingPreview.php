<?php

namespace Modules\BusManagement\Service\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAccAccount;
use Modules\BusManagement\Entities\BusAccBook;
use Modules\BusManagement\Entities\BusAccDocument;
use Modules\BusManagement\Entities\BusAccEntry;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusVehicle;
use Throwable;

/**
 * What an action will do, shown before it is done. The preview runs the real action (the same
 * code, the same checks) inside a transaction, reads what it wrote, and rolls it all back. So it
 * cannot disagree with what confirming does. A refusal comes back as a problem, and confirming
 * stays off until it is fixed.
 *
 * Shape: ok, problems[], warnings[], notes[], summary [[label, value]], entries [{number, date,
 * memo, lines [{account, debit, credit, tags}]}], effects [{title, columns, numeric, rows}].
 */
class AccountingPreview
{
    public function __construct(protected Ledger $ledger, protected LedgerSync $sync, protected AccountingService $service)
    {
    }

    /** Runs $action rolled back. $action returns the document (or an array for month actions). */
    public function run(BusAgency $agency, string $title, callable $action, ?callable $extra = null): array
    {
        $out = ['ok' => true, 'title' => $title, 'problems' => [], 'warnings' => [], 'notes' => [], 'summary' => [], 'entries' => [], 'effects' => []];
        // Automatic postings first, for real, so the preview shows only this action.
        $this->sync->sync($agency);
        $books = $this->ledger->books($agency);
        $fromNumber = $books ? $books->next_number : 1;
        $accounts = $books ? BusAccAccount::where('agency_id', $agency->id)->get()->keyBy(fn($a) => (string)$a->id) : collect();
        $before = $books ? $this->balances($agency) : [];

        DB::beginTransaction();
        try {
            $result = $action();
            $accounts = BusAccAccount::where('agency_id', $agency->id)->get()->keyBy(fn($a) => (string)$a->id);
            $entries = BusAccEntry::with('lines')->where('agency_id', $agency->id)
                ->where('number', '>=', 'JE-' . str_pad((string)$fromNumber, 6, '0', STR_PAD_LEFT))->orderBy('number')->get();
            foreach ($entries as $e) {
                $out['entries'][] = $this->entry($e, $accounts);
            }
            if ($result instanceof BusAccDocument) {
                $out['summary'] = $this->summary($result);
                $this->warnings($agency, $result, $out);
            }
            $after = $this->balances($agency);
            $rows = [];
            $touched = $entries->flatMap(fn($e) => $e->lines->pluck('account_id'))->map(fn($id) => (string)$id)->unique();
            foreach ($touched as $id) {
                $a = $accounts[$id] ?? null;
                if (!$a || in_array($a->type, ['revenue', 'expense'], true)) {
                    continue;
                }
                $b = $before[$id] ?? 0;
                $n = $after[$id] ?? 0;
                $rows[] = [$a->label(), $this->plain($b), $this->plain($n - $b), $this->plain($n)];
                if ($a->money && $n < -0.004 && $b >= -0.004) {
                    $out['warnings'][] = translate(key: '{account} goes below zero in the books. Check the account chosen, or record the money that came in first.', replace: ['account' => $a->label()]);
                }
            }
            if ($rows) {
                $out['effects'][] = ['title' => translate('Balances'), 'columns' => [translate('Account'), translate('Before'), translate('Change'), translate('After')], 'numeric' => [1, 2, 3], 'rows' => $rows];
            }
            if ($extra) {
                $extra($out, $result);
            }
        } catch (ValidationException $e) {
            $out['ok'] = false;
            $out['problems'] = array_values(array_unique(array_merge(...array_values($e->errors()))));
        } catch (Throwable $e) {
            report($e);
            $out['ok'] = false;
            $out['problems'][] = translate('This could not be checked. Try again, or contact E-Ride if it keeps happening.');
        } finally {
            DB::rollBack();
        }
        return $out;
    }

    /** Balances of the branch's accounts (normal direction). */
    public function balances(BusAgency $agency): array
    {
        $types = BusAccAccount::where('agency_id', $agency->id)->pluck('type', 'id');
        return BusAccLine::where('agency_id', $agency->id)->groupBy('account_id')->selectRaw('account_id, sum(debit) - sum(credit) as net')->pluck('net', 'account_id')
            ->mapWithKeys(fn($net, $id) => [(string)$id => round(in_array($types[$id] ?? 'asset', ['asset', 'expense'], true) ? (float)$net : -(float)$net, 2)])->all();
    }

    public function entry(BusAccEntry $e, $accounts): array
    {
        return [
            'number' => $e->number,
            'date' => $e->date->format('d/m/Y'),
            'memo' => $e->memo,
            'lines' => $e->lines->map(fn(BusAccLine $l) => [
                'account' => ($accounts[(string)$l->account_id] ?? null)?->label() ?? '?',
                'debit' => $l->debit > 0 ? $this->plain($l->debit) : '',
                'credit' => $l->credit > 0 ? $this->plain($l->credit) : '',
                'tags' => $this->tagText($l),
            ])->all(),
        ];
    }

    public function tagText(BusAccLine $l): string
    {
        $parts = [];
        if ($l->vehicle_id) {
            $parts[] = BusVehicle::whereKey($l->vehicle_id)->value('registration');
        }
        if ($l->destination_id) {
            $parts[] = '→ ' . BusDestination::whereKey($l->destination_id)->value('city');
        }
        if ($l->staff_user_id) {
            $parts[] = $this->service->userName($l->staff_user_id);
        }
        if ($l->memo) {
            $parts[] = $l->memo;
        }
        return implode(' · ', array_filter($parts));
    }

    protected function summary(BusAccDocument $d): array
    {
        $rows = [[translate('Document'), $d->typeLabel() . ' ' . $d->number], [translate('Date'), $d->date->format('d/m/Y')]];
        $acc = fn($id) => $id ? BusAccAccount::find($id)?->label() : null;
        $add = function ($label, $value) use (&$rows) {
            if ($value !== null && $value !== '') {
                $rows[] = [$label, $value];
            }
        };
        switch ($d->type) {
            case BusAccDocument::EXPENSE:
                $add(translate('Category'), $acc($d->account_id));
                $add(translate('Amount'), $this->money($d->amount));
                $add(translate('Paid from'), $d->money_account_id ? $acc($d->money_account_id) : translate('Not paid yet: owed to the supplier'));
                $add(translate('Supplier'), $d->supplier);
                $add(translate('Due on'), $d->due_on?->format('d/m/Y'));
                break;
            case BusAccDocument::BILL_PAYMENT:
                $add(translate('Bill'), $d->parent?->number . ' · ' . $d->supplier);
                $add(translate('Amount'), $this->money($d->amount));
                $add(translate('Paid from'), $acc($d->money_account_id));
                $add(translate('Still owed on the bill after this'), $this->money((float)$d->parent?->fresh()->owed));
                break;
            case BusAccDocument::INCOME:
                $add(translate('Category'), $acc($d->account_id));
                $add(translate('Amount'), $this->money($d->amount));
                $add(translate('Received into'), $acc($d->money_account_id));
                break;
            case BusAccDocument::TRANSFER:
                $add(translate('From'), $acc($d->money_account_id));
                $add(translate('To'), $acc($d->to_account_id));
                $add(translate('Amount'), $this->money($d->amount));
                $add(translate('Charge'), $d->fee > 0 ? $this->money($d->fee) : null);
                break;
            case BusAccDocument::CASH_COUNT:
                $add(translate('Agent'), $this->service->userName($d->staff_user_id));
                $add(translate('Should hold'), $this->money($d->expected));
                $add(translate('Counted'), $this->money($d->counted));
                $add(translate('Difference'), $this->money($d->counted - $d->expected));
                $add(translate('Put in'), $acc($d->to_account_id));
                break;
            case BusAccDocument::CHECK:
                $add(translate('Account'), $acc($d->account_id));
                $add(translate('Balance on the statement'), $this->money($d->counted));
                $add(translate('Money in, not on the statement yet'), $this->money($d->lines['in_transit'] ?? 0));
                $add(translate('Payments out, not on the statement yet'), $this->money($d->lines['outstanding'] ?? 0));
                $add(translate('The books should show'), $this->money($d->counted + ($d->lines['in_transit'] ?? 0) - ($d->lines['outstanding'] ?? 0)));
                $add(translate('Balance in the books'), $this->money($d->expected));
                $add(translate('Movements matched'), (string)count($d->lines['cleared'] ?? []));
                break;
            case BusAccDocument::JOURNAL:
                $add(translate('Total'), $this->money($d->amount));
                $add(translate('Reverses itself on'), $d->reverse_on?->format('d/m/Y'));
                break;
            case BusAccDocument::OPENING:
                $add(translate('Books start on'), $d->date->format('d/m/Y'));
                break;
        }
        if ($d->vehicle_id) {
            $add(translate('Bus'), BusVehicle::whereKey($d->vehicle_id)->value('registration'));
        }
        if ($d->destination_id) {
            $add(translate('Route'), BusDestination::whereKey($d->destination_id)->value('city'));
        }
        if ($d->departure_id && ($dep = BusDeparture::find($d->departure_id))) {
            $add(translate('Trip'), $dep->leavesAt()->format('d/m H:i'));
        }
        $add(translate('Reference'), $d->reference);
        $add(translate('Note'), $d->note);
        return $rows;
    }

    protected function warnings(BusAgency $agency, BusAccDocument $d, array &$out): void
    {
        if (in_array($d->type, [BusAccDocument::EXPENSE, BusAccDocument::INCOME], true)) {
            $same = BusAccDocument::where('agency_id', $agency->id)->where('type', $d->type)->where('status', 'posted')->where('id', '!=', $d->id)
                ->where('account_id', $d->account_id)->where('amount', $d->amount)->whereBetween('date', [$d->date->copy()->subDays(3), $d->date->copy()->addDays(3)])->first();
            if ($same) {
                $out['warnings'][] = translate(key: '{number} has the same category and amount a few days apart. Make sure this is not the same one twice.', replace: ['number' => $same->number]);
            }
        }
        if ($d->type === BusAccDocument::EXPENSE && blank($d->reference)) {
            $out['notes'][] = translate('No receipt or invoice number: adding it helps spot a receipt entered twice.');
        }
        if ($d->type === BusAccDocument::CASH_COUNT && abs($d->counted - $d->expected) >= 0.005) {
            $out['warnings'][] = $d->counted < $d->expected
                ? translate(key: 'The cash is short by {amount}. It is posted as Cash short on this agent.', replace: ['amount' => $this->money($d->expected - $d->counted)])
                : translate(key: 'The cash is over by {amount}. It is posted as Cash over on this agent.', replace: ['amount' => $this->money($d->counted - $d->expected)]);
        }
        if ($d->type === BusAccDocument::CHECK) {
            $out['notes'][] = empty($out['entries']) ? translate('The statement agrees with the books.') : translate('Once the difference is posted, the statement agrees with the books.');
        }
        if (!$d->date->isSameMonth(today())) {
            $out['notes'][] = translate(key: 'Dated in {month}, not this month.', replace: ['month' => $d->date->translatedFormat('F Y')]);
        }
    }

    /** "12 500 FCFA", for the summary and messages. */
    public function money(float $amount): string
    {
        return \Modules\BusManagement\Lib\AccFormat::n($amount) . ' FCFA';
    }

    /** "12 500", for table columns (the heading says FCFA). */
    public function plain(float $amount): string
    {
        return \Modules\BusManagement\Lib\AccFormat::n($amount);
    }
}
