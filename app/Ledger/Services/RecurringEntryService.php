<?php

namespace App\Ledger\Services;

use App\Ledger\Models\RecurringJournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recurring entries (écritures récurrentes): paxhitestbed
 * App\Services\Accounting\RecurringEntryService, its corrected working tree.
 * The entry itself is written by RecurringJournalEntry::generateJournalEntry
 * (through LedgerPoster). They are run by hand, as in paxhitestbed (Process
 * now / Process all due, through the review dialog); nothing runs them from
 * the scheduler.
 */
class RecurringEntryService
{
    /** Process every template due on $date (one occurrence each). */
    public function processDueEntries($date = null, ?string $userId = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::now();
        $results = ['processed' => 0, 'created' => 0, 'errors' => [], 'entries' => []];

        foreach (RecurringJournalEntry::dueToRun($date)->with('lines')->orderBy('next_run_date')->get() as $template) {
            try {
                $entry = $template->generateJournalEntry($date, $userId);
                if ($entry) {
                    $results['entries'][] = $entry;
                    $results['created']++;
                }
                $results['processed']++;
            } catch (\Throwable $e) {
                $results['errors'][] = ['recurring_entry_id' => $template->id, 'name' => $template->name, 'error' => $e->getMessage()];
                Log::warning('Recurring entry not processed', ['business_id' => $template->business_id, 'id' => $template->id, 'error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /** Process one template, when it is due. */
    public function processEntry(RecurringJournalEntry $template, $date = null, ?string $userId = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::now();
        if (! $template->shouldRun($date)) {
            return null;
        }

        return $template->generateJournalEntry($date, $userId);
    }

    /** @throws \RuntimeException when the lines do not make an entry */
    public function createTemplate(array $data): RecurringJournalEntry
    {
        return DB::transaction(function () use ($data) {
            $lines = $this->cleanLines($data['lines'] ?? []);

            $template = RecurringJournalEntry::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'journal_type' => $data['journal_type'] ?? 'general',
                'frequency' => $data['frequency'],
                'day_of_month' => $this->intOrNull($data['day_of_month'] ?? null),
                'day_of_week' => $this->intOrNull($data['day_of_week'] ?? null),
                'month_of_year' => $this->intOrNull($data['month_of_year'] ?? null),
                'start_date' => $data['start_date'],
                'end_date' => ($data['end_date'] ?? null) ?: null,
                'next_run_date' => $data['next_run_date'] ?? $data['start_date'],
                'occurrences' => $this->intOrNull($data['occurrences'] ?? null),
                'is_active' => $data['is_active'] ?? true,
                'auto_post' => ! empty($data['auto_post']),
                'total_debit' => round(array_sum(array_column($lines, 'debit')), 2),
                'total_credit' => round(array_sum(array_column($lines, 'credit')), 2),
                'created_by' => auth()->id(),
            ]);
            $this->writeLines($template, $lines);
            $template->load('lines')->validateLines();

            return $template;
        });
    }

    /** @throws \RuntimeException */
    public function updateTemplate(RecurringJournalEntry $template, array $data): RecurringJournalEntry
    {
        return DB::transaction(function () use ($template, $data) {
            $lines = $this->cleanLines($data['lines'] ?? []);
            $fields = [
                'name' => $data['name'] ?? $template->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $template->description,
                'journal_type' => $data['journal_type'] ?? $template->journal_type,
                'frequency' => $data['frequency'] ?? $template->frequency,
                'day_of_month' => array_key_exists('day_of_month', $data) ? $this->intOrNull($data['day_of_month']) : $template->day_of_month,
                'end_date' => array_key_exists('end_date', $data) ? ($data['end_date'] ?: null) : $template->end_date,
                'occurrences' => array_key_exists('occurrences', $data) ? $this->intOrNull($data['occurrences']) : $template->occurrences,
                // the form always sends the form: an unticked box is "no" (paxhitestbed kept the old value)
                'auto_post' => ! empty($data['auto_post']),
                'total_debit' => round(array_sum(array_column($lines, 'debit')), 2),
                'total_credit' => round(array_sum(array_column($lines, 'credit')), 2),
                'updated_by' => auth()->id(),
            ];
            // Before its first run the start date may still move, and the first run with it.
            if (! empty($data['start_date']) && (int) $template->occurrences_completed === 0) {
                $fields['start_date'] = $data['start_date'];
                $fields['next_run_date'] = $data['start_date'];
            }
            $template->update($fields);
            $template->lines()->delete();
            $this->writeLines($template, $lines);
            $template->load('lines')->validateLines();

            return $template->fresh(['lines']);
        });
    }

    public function pause(RecurringJournalEntry $template): RecurringJournalEntry
    {
        $template->update(['is_active' => false, 'notes' => $this->note($template, 'Paused on: '.now()->format('Y-m-d H:i:s'))]);

        return $template;
    }

    public function resume(RecurringJournalEntry $template, $newNextRunDate = null): RecurringJournalEntry
    {
        $update = ['is_active' => true, 'notes' => $this->note($template, 'Resumed on: '.now()->format('Y-m-d H:i:s'))];
        if ($newNextRunDate) {
            $update['next_run_date'] = Carbon::parse($newNextRunDate)->toDateString();
        }
        $template->update($update);

        return $template;
    }

    public function skipNext(RecurringJournalEntry $template): RecurringJournalEntry
    {
        $template->update([
            'next_run_date' => $template->calculateNextRunDate()->toDateString(),
            'notes' => $this->note($template, 'Skipped occurrence on: '.now()->format('Y-m-d')),
        ]);

        return $template;
    }

    public function getUpcoming(int $days = 30)
    {
        return RecurringJournalEntry::active()
            ->whereDate('next_run_date', '<=', Carbon::now()->addDays($days)->toDateString())
            ->orderBy('next_run_date')
            ->get();
    }

    public function getSummary(): array
    {
        return [
            'total' => RecurringJournalEntry::count(),
            'active' => RecurringJournalEntry::active()->count(),
            'inactive' => RecurringJournalEntry::where('is_active', false)->count(),
            'due_today' => RecurringJournalEntry::dueToRun(Carbon::today())->count(),
            'due_this_week' => RecurringJournalEntry::dueToRun(Carbon::now()->endOfWeek())->count(),
            'by_frequency' => RecurringJournalEntry::active()->select('frequency', DB::raw('COUNT(*) as count'))
                ->groupBy('frequency')->pluck('count', 'frequency')->toArray(),
            'total_monthly_amount' => $this->calculateMonthlyTotal(),
        ];
    }

    protected function calculateMonthlyTotal(): float
    {
        $total = 0;
        foreach (RecurringJournalEntry::active()->get() as $entry) {
            $total += (float) $entry->total_debit * $this->getMonthlyMultiplier($entry->frequency);
        }

        return round($total, 2);
    }

    protected function getMonthlyMultiplier(string $frequency): float
    {
        return [
            RecurringJournalEntry::FREQUENCY_DAILY => 30,
            RecurringJournalEntry::FREQUENCY_WEEKLY => 4.33,
            RecurringJournalEntry::FREQUENCY_BIWEEKLY => 2.17,
            RecurringJournalEntry::FREQUENCY_MONTHLY => 1,
            RecurringJournalEntry::FREQUENCY_QUARTERLY => 0.33,
            RecurringJournalEntry::FREQUENCY_SEMIANNUALLY => 0.17,
            RecurringJournalEntry::FREQUENCY_ANNUALLY => 0.083,
        ][$frequency] ?? 1;
    }

    /** A copy of the template, counters reset (paxhitestbed duplicate). */
    public function duplicate(RecurringJournalEntry $template, array $overrides = []): RecurringJournalEntry
    {
        $data = $template->only(['name', 'description', 'journal_type', 'frequency', 'day_of_month', 'day_of_week', 'month_of_year',
            'end_date', 'occurrences', 'is_active', 'auto_post']);
        $data['start_date'] = $overrides['start_date'] ?? $template->start_date->toDateString();
        $data['next_run_date'] = $data['start_date'];
        $data['end_date'] = $template->end_date ? $template->end_date->toDateString() : null;
        $data['lines'] = $template->lines->map(fn ($l) => [
            'account_id' => $l->account_id, 'debit' => $l->debit, 'credit' => $l->credit, 'description' => $l->description,
        ])->all();

        return $this->createTemplate($data);
    }

    /* ------------------------------------------------------------------ */

    /** Lines with an account and an amount, in the order typed. */
    protected function cleanLines(array $lines): array
    {
        $out = [];
        foreach (array_values($lines) as $l) {
            $dr = round((float) ($l['debit'] ?? 0), 2);
            $cr = round((float) ($l['credit'] ?? 0), 2);
            if (empty($l['account_id']) || ($dr == 0 && $cr == 0)) {
                continue;
            }
            if ($dr < 0 || $cr < 0) {
                throw new \RuntimeException(__('ledger.err_negative'));
            }
            $out[] = ['account_id' => (int) $l['account_id'], 'debit' => $dr, 'credit' => $cr, 'description' => $l['description'] ?? null];
        }

        return $out;
    }

    protected function writeLines(RecurringJournalEntry $template, array $lines): void
    {
        foreach ($lines as $i => $line) {
            $template->lines()->create($line + ['line_number' => $i + 1, 'business_id' => $template->business_id]);
        }
    }

    protected function intOrNull($v): ?int
    {
        return ($v === null || $v === '') ? null : (int) $v;
    }

    protected function note(RecurringJournalEntry $template, string $line): string
    {
        return trim(($template->notes ? $template->notes."\n" : '').$line);
    }
}
