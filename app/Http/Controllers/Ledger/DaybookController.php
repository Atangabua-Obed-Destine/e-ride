<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Exports\DaybookExport;
use App\Ledger\Ledger;
use App\Ledger\Models\AccountingPeriod;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Services\CustomerCollection;
use App\Ledger\Services\DaybookConfidence;
use App\Ledger\Services\DaybookService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The cash analysis book (paxhitestbed Admin\DaybookController): the book,
 * its Monthly Summary, the Trends & Analysis page and their PDF / Excel
 * copies. Same screens, buttons and wording.
 *
 * Where paxhitestbed opens a book on a budget and one of its months,
 * PosInnova opens it on a fiscal year and one of its months, and adds a cash
 * box picker: the book is the money in and out
 * of each cash, bank and mobile-money account, read from the posted ledger
 * (App\Ledger\Services\DaybookService). The Monthly Summary adds the month
 * against the Income & Expenditure sheet covering it (Budget, Monthly,
 * BB Forward, Cumulative, Balance by budget line). Nothing here writes to the books.
 */
class DaybookController extends LedgerController
{
    protected DaybookService $daybook;

    public function __construct(DaybookService $daybook)
    {
        parent::__construct();
        $this->daybook = $daybook;
    }

    /** The book itself, one month at a time. */
    public function index(Request $request)
    {
        $this->allow('daybook-view');
        $data = $this->bookData($request);
        if (! $data) {
            return $this->noYear();
        }

        return view('ledger.daybook.index', $data);
    }

    /** A month by its calendar name (YYYY-MM): the book on the period containing it. */
    public function month(Request $request, string $month)
    {
        $this->allow('daybook-view');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return $this->err(route('ledger.daybook.index'), __('ledger_daybook.err_bad_month', ['month' => $month]));
        }
        $day = $month.'-15';
        $period = FiscalYear::periodForDate($day);
        if (! $period) {
            return $this->err(route('ledger.daybook.index'), __('ledger_daybook.err_no_year_for', [
                'month' => Carbon::parse($day)->locale(app()->getLocale())->isoFormat('MMMM YYYY'),
            ]));
        }

        return redirect()->route('ledger.daybook.index', array_filter([
            'fiscal_year_id' => $period->fiscal_year_id, 'period_id' => $period->id, 'account_id' => $request->input('account_id'),
        ]));
    }

    /** The Monthly Summary: Monthly · BB Forward · Cumulative. */
    public function summary(Request $request)
    {
        $this->allow('daybook-view');
        $data = $this->summaryData($request);
        if (! $data) {
            return $this->noYear();
        }

        return view('ledger.daybook.summary', $data);
    }

    /** The year as a shape: trends, cash position, and where the money goes. */
    public function analysis(Request $request)
    {
        $this->allow('daybook-view');
        $context = $this->context($request);
        if (! $context) {
            return $this->noYear();
        }

        return view('ledger.daybook.analysis', $context + $this->analysisData($context) + [
            'title' => __('ledger_daybook.trends_analysis'),
        ]);
    }

    /**
     * The analysis as a pack to take into the room. A POST because the charts
     * come with it: the page captures each chart as a PNG and posts the
     * images. The pack renders whether or not they arrive: every chart is
     * backed by its own table.
     */
    public function analysisPdf(Request $request)
    {
        $this->allow('daybook-view');
        $context = $this->context($request);
        abort_unless($context, 404);

        $images = $request->input('charts', []);
        // Only data: PNGs this page could have produced; never a remote src.
        $images = collect(is_array($images) ? $images : [])
            ->filter(fn ($src) => is_string($src) && str_starts_with($src, 'data:image/png;base64,'))
            ->filter(fn ($src) => strlen($src) < 4000000)
            ->all();

        $data = $context + $this->analysisData($context) + [
            'charts' => $images,
            'generatedAt' => now(),
            'title' => __('ledger_daybook.financial_analysis'),
        ];

        return $this->renderPdf('ledger.daybook.analysis-pdf', $data, 'P', 'analysis-'.$context['year']->name.'.pdf');
    }

    /** The book as a document, for signing and filing. */
    public function pdf(Request $request)
    {
        $this->allow('daybook-view');
        $data = $this->bookData($request);
        abort_unless($data, 404);

        return $this->renderPdf('ledger.daybook.pdf', $data, 'L', $this->filename($data['period'], 'daybook').'.pdf');
    }

    /** The book as a workbook: the month, then its summary. */
    public function excel(Request $request)
    {
        $this->allow('daybook-view');
        $data = $this->bookData($request);
        abort_unless($data, 404);
        $summary = $this->summaryData($request);

        return Excel::download(new DaybookExport($data + ['businessName' => $this->businessName()], $summary),
            $this->filename($data['period'], 'daybook').'.xlsx');
    }

    /* ===================================================================
     |  Shared assembly
     |===================================================================*/

    /**
     * The fiscal year, month and cash box a request is asking about. Default:
     * the year and month of today; else the last month that carries movement;
     * else the first.
     */
    protected function context(Request $request): ?array
    {
        $years = FiscalYear::orderByDesc('start_date')->get();
        if ($years->isEmpty()) {
            return null;
        }
        $year = $request->filled('fiscal_year_id') ? $years->firstWhere('id', (int) $request->input('fiscal_year_id')) : null;
        $year = $year ?: (FiscalYear::forDate(now()->toDateString()) ?: (FiscalYear::getActiveFiscalYear() ?: $years->first()));

        $periods = $this->daybook->periodsFor($year);
        if ($periods->isEmpty()) {
            return null;
        }

        $boxes = $this->daybook->cashAccounts();
        $accountId = $request->filled('account_id') ? (int) $request->input('account_id') : null;
        $account = $accountId ? $boxes->firstWhere('id', $accountId) : null;
        $cashIds = $account ? [(int) $account->id] : $boxes->pluck('id')->map(fn ($v) => (int) $v)->all();

        $period = $request->filled('period_id') ? $periods->firstWhere('id', (int) $request->input('period_id')) : null;
        if (! $period) {
            $today = now()->toDateString();
            $period = $periods->first(fn (AccountingPeriod $p) => $p->start_date->toDateString() <= $today && $p->end_date->toDateString() >= $today);
        }
        if (! $period && $cashIds) {
            $last = \DB::table('journal_entry_lines as l')->join('journal_entries as je', 'je.id', '=', 'l.journal_entry_id')
                ->where('l.business_id', Ledger::businessId())->where('je.business_id', Ledger::businessId())
                ->where('je.is_posted', 1)->whereNull('je.deleted_at')->whereIn('l.account_id', $cashIds)
                ->whereBetween('je.entry_date', [$year->start_date->toDateString(), $year->end_date->toDateString()])
                ->max('je.entry_date');
            $period = $last ? $periods->first(fn ($p) => $p->start_date->toDateString() <= $last && $p->end_date->toDateString() >= $last) : null;
        }
        $period = $period ?: $periods->first();

        return [
            'year' => $year,
            'years' => $years,
            'periods' => $periods,
            'period' => $period,
            'boxes' => $boxes,
            'account' => $account,
            'cashIds' => $cashIds,
            'query' => array_filter(['fiscal_year_id' => $year->id, 'period_id' => $period->id, 'account_id' => $account->id ?? null]),
        ];
    }

    protected function bookData(Request $request): ?array
    {
        $context = $this->context($request);
        if (! $context) {
            return null;
        }
        $period = $context['period'];
        $from = $period->start_date->toDateString();
        $to = $period->end_date->toDateString();
        $rows = $this->daybook->rows($from, $to, $context['cashIds']);

        $in = 0.0;
        $out = 0.0;
        $unanalysed = [];
        foreach ($rows as $row) {
            $row['direction'] === DaybookService::IN ? $in += $row['amount'] : $out += $row['amount'];
            if ($row['unanalysed']) {
                $unanalysed[] = $row;
            }
        }
        $ids = array_merge(array_column($rows, 'line_id'), array_column($rows, 'cash_account_id'));
        $boxes = $this->daybook->balances($from, $to, $context['cashIds']);

        return $context + [
            'title' => __('ledger_daybook.daybook'),
            'rows' => $rows,
            'lines' => $this->daybook->accounts(array_filter($ids)),
            'totalIn' => round($in, 2),
            'totalOut' => round($out, 2),
            'unanalysed' => $unanalysed,
            'balances' => $boxes,
            'opening' => round(array_sum(array_column($boxes, 'opening')), 2),
            'closing' => round(array_sum(array_column($boxes, 'closing')), 2),
        ];
    }

    protected function summaryData(Request $request): ?array
    {
        $context = $this->context($request);
        if (! $context) {
            return null;
        }

        // paxhitestbed's Budget and Balance columns: the month against the
        // Income & Expenditure sheet (the institutional budget) covering it
        $period = $context['period'];
        $budget = \App\Ledger\Models\Budget::where('is_institutional', true)->where('status', '!=', 'cancelled')
            ->whereDate('start_date', '<=', $period->end_date->toDateString())
            ->whereDate('end_date', '>=', $period->start_date->toDateString())
            ->orderByDesc('start_date')->first();

        return $context + [
            'title' => __('ledger_daybook.monthly_summary_title'),
            'summary' => $this->daybook->monthlySummary($context['year'], $context['period'], $context['cashIds']),
            'againstBudget' => $budget ? app(\App\Ledger\Services\BudgetActualsService::class)
                ->monthAgainstBudget($budget, $period->start_date->toDateString(), $period->end_date->toDateString()) : null,
        ];
    }

    protected function analysisData(array $context): array
    {
        $year = $context['year'];
        $analysis = $this->daybook->analysis($year, $context['cashIds']);
        $confidence = app(DaybookConfidence::class);
        $checks = $confidence->checks($analysis['rows']);

        return [
            'analysis' => $analysis,
            'collection' => app(CustomerCollection::class)->forYear($year->start_date->toDateString(), $year->end_date->toDateString(),
                $analysis['rows'], $analysis['months']),
            'checks' => $checks,
            'allClear' => $confidence->allClear($checks),
            'concerns' => $confidence->concerns($checks),
        ];
    }

    private function noYear()
    {
        return $this->err(route('ledger.fiscal-years.index'), __('ledger_daybook.err_no_year'));
    }

    protected function filename(AccountingPeriod $period, string $prefix): string
    {
        return $prefix.'-'.$period->start_date->format('Y-m');
    }

    private function businessName(): string
    {
        return (string) (optional(session('business'))->name
            ?: \DB::table('ledger_businesses')->where('id', Ledger::businessId())->value('name'));
    }

    /** Render a view to PDF with mpdf (as PosInnova's other reports do) and send it as a download. */
    private function renderPdf(string $view, array $data, string $orientation, string $filename)
    {
        $data['businessName'] = $this->businessName();
        $html = view($view, $data)->render();
        $mpdf = $this->getMpdf($orientation);
        $mpdf->SetTitle(strip_tags($data['title'] ?? $filename));
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
