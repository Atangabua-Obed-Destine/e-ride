<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Exports\LedgerRowsExport;
use App\Ledger\Ledger;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Services\AgingReportService;
use App\Ledger\Services\CashFlowService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Accounting reports: paxhitestbed's Admin\AccountingReportsController, same
 * screens, filters, buttons and exports: the reports dashboard, receivables
 * and payables aging, the cash flow statement and its comparative version.
 *
 * Not ported: Student Fee Aging (school only), Budget vs Actual (built with
 * the budgets). Aging reads POS documents (AgingReportService); the cash flow
 * statement reads the posted ledger (CashFlowService). Nothing here posts.
 *
 * PDF: mpdf, as PosInnova's other reports (paxhitestbed: dompdf, or a printable
 * page). Excel: a real .xlsx with the columns of paxhitestbed's CSV.
 */
class AccountingReportsController extends LedgerController
{
    private const TYPES = ['receivables_aging', 'payables_aging', 'cash_flow', 'comparative_cash_flow'];

    protected $agingService;

    protected $cashFlowService;

    public function __construct(AgingReportService $agingService, CashFlowService $cashFlowService)
    {
        parent::__construct();
        $this->agingService = $agingService;
        $this->cashFlowService = $cashFlowService;
    }

    /** A date from the query string (Y-m-d), or $default. */
    private function date(Request $request, string $key, ?string $default = null): ?string
    {
        $v = $request->input($key);
        if (! $v) {
            return $default;
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function fail(Request $request, Exception $e, string $what)
    {
        Log::error('Error generating '.$what, ['exception' => $e->getMessage()]);
        if ($request->ajax()) {
            return response()->json(['success' => false, 'message' => __('ledger_reports.error_generating_report')], 500);
        }

        return $this->err(route('ledger.accounting-reports.index'), __('ledger_reports.error_generating_report'));
    }

    /**
     * Reports dashboard
     */
    public function index()
    {
        $this->allow('accounting-report-view', 'cash-flow-view');

        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        $currentFiscalYear = FiscalYear::getActiveFiscalYear();

        return view('ledger.accounting-reports.index', compact('fiscalYears', 'currentFiscalYear'));
    }

    /**
     * Accounts Receivable Aging Report
     */
    public function receivablesAging(Request $request)
    {
        return $this->aging($request, 'receivables');
    }

    /**
     * Accounts Payable Aging Report
     */
    public function payablesAging(Request $request)
    {
        return $this->aging($request, 'payables');
    }

    private function aging(Request $request, string $kind)
    {
        $this->allow('accounting-report-view');
        try {
            $asOfDate = $this->date($request, 'as_of_date', now()->format('Y-m-d'));
            $report = $this->agingService->getAgingSummaryWithCharts($kind, $asOfDate);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'data' => $report]);
            }

            return view('ledger.accounting-reports.'.$kind.'-aging', compact('report', 'asOfDate'));
        } catch (Exception $e) {
            return $this->fail($request, $e, $kind.' aging report');
        }
    }

    /** The year asked for, else the active one, else the latest. */
    private function year(Request $request): ?FiscalYear
    {
        $id = $request->input('fiscal_year_id');
        $fy = $id ? FiscalYear::find($id) : null;

        return $fy ?: (FiscalYear::getActiveFiscalYear() ?: FiscalYear::orderByDesc('start_date')->first());
    }

    /**
     * Cash Flow Statement
     */
    public function cashFlowStatement(Request $request)
    {
        $this->allow('accounting-report-view', 'cash-flow-view');
        $fiscalYear = $this->year($request);
        if (! $fiscalYear) {
            return $this->err(route('ledger.fiscal-years.index'), __('ledger_reports.no_active_fiscal_year'));
        }
        try {
            $startDate = $this->date($request, 'start_date', $fiscalYear->start_date->toDateString());
            $endDate = $this->date($request, 'end_date', $fiscalYear->end_date->toDateString());

            $statement = $this->cashFlowService->generateCashFlowStatement($fiscalYear->id, $startDate, $endDate);
            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

            if ($request->ajax()) {
                return response()->json(['success' => true, 'data' => $statement]);
            }

            return view('ledger.accounting-reports.cash-flow-statement', compact('statement', 'fiscalYears', 'fiscalYear', 'startDate', 'endDate'));
        } catch (Exception $e) {
            return $this->fail($request, $e, 'cash flow statement');
        }
    }

    /**
     * Comparative Cash Flow Statement
     */
    public function comparativeCashFlow(Request $request)
    {
        $this->allow('accounting-report-view', 'cash-flow-view');
        $currentFiscalYear = $this->year($request);
        if (! $currentFiscalYear) {
            return $this->err(route('ledger.fiscal-years.index'), __('ledger_reports.no_active_fiscal_year'));
        }
        try {
            $statement = $this->cashFlowService->generateComparativeCashFlowStatement($currentFiscalYear->id, $request->input('previous_fiscal_year_id'));
            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

            if ($request->ajax()) {
                return response()->json(['success' => true, 'data' => $statement]);
            }

            return view('ledger.accounting-reports.comparative-cash-flow', compact('statement', 'fiscalYears', 'currentFiscalYear'));
        } catch (Exception $e) {
            return $this->fail($request, $e, 'comparative cash flow');
        }
    }

    /** Which report an export is for (paxhitestbed accepts report or report_type, with - or _). */
    private function reportType(Request $request): ?string
    {
        $type = str_replace('-', '_', (string) ($request->input('report') ?? $request->input('report_type')));

        return in_array($type, self::TYPES, true) ? $type : null;
    }

    /** The figures of a report, exactly as its screen shows them. */
    private function reportData(Request $request, string $type): array
    {
        switch ($type) {
            case 'receivables_aging':
                $this->allow('accounting-report-view');

                return $this->agingService->getReceivablesAging($this->date($request, 'as_of_date'));
            case 'payables_aging':
                $this->allow('accounting-report-view');

                return $this->agingService->getPayablesAging($this->date($request, 'as_of_date'));
            case 'cash_flow':
                $this->allow('accounting-report-view', 'cash-flow-export');
                $fy = $this->year($request);
                abort_unless($fy, 404);

                return $this->cashFlowService->generateCashFlowStatement($fy->id, $this->date($request, 'start_date'), $this->date($request, 'end_date'));
            case 'comparative_cash_flow':
                $this->allow('accounting-report-view', 'cash-flow-export');
                $fy = $this->year($request);
                abort_unless($fy, 404);

                return $this->cashFlowService->generateComparativeCashFlowStatement($fy->id, $request->input('previous_fiscal_year_id'));
        }

        return [];
    }

    /**
     * Export report to PDF
     */
    public function exportPdf(Request $request)
    {
        $reportType = $this->reportType($request);
        if (! $reportType) {
            return $this->err(route('ledger.accounting-reports.index'), __('ledger_reports.invalid_report_type'));
        }
        $data = $this->reportData($request, $reportType);

        try {
            $views = [
                'receivables_aging' => ['ledger.accounting-reports.pdf.aging', 'L', 'receivables_aging_report'],
                'payables_aging' => ['ledger.accounting-reports.pdf.aging', 'L', 'payables_aging_report'],
                'cash_flow' => ['ledger.accounting-reports.pdf.cash-flow', 'P', 'cash_flow_statement'],
                'comparative_cash_flow' => ['ledger.accounting-reports.pdf.comparative-cash-flow', 'L', 'comparative_cash_flow_statement'],
            ];
            [$view, $orientation, $title] = $views[$reportType];
            $businessName = optional(session('business'))->name
                ?: \DB::table('ledger_businesses')->where('id', Ledger::businessId())->value('name');
            $html = view($view, ['data' => $data, 'reportType' => $reportType, 'businessName' => $businessName,
                'title' => __('ledger_reports.'.$title)])->render();

            $mpdf = $this->getMpdf($orientation);
            $mpdf->SetTitle(__('ledger_reports.'.$title));
            $mpdf->WriteHTML($html);
            $filename = $reportType.'_'.date('Y-m-d').'.pdf';

            return response($mpdf->Output($filename, 'S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (Exception $e) {
            Log::error('Error exporting report to PDF', ['exception' => $e->getMessage()]);

            return $this->err(route('ledger.accounting-reports.index'), __('ledger_reports.error_exporting_report'));
        }
    }

    /**
     * Export report to Excel
     */
    public function exportExcel(Request $request)
    {
        $reportType = $this->reportType($request);
        if (! $reportType) {
            return $this->err(route('ledger.accounting-reports.index'), __('ledger_reports.invalid_report_type'));
        }
        $data = $this->reportData($request, $reportType);

        try {
            $rows = $this->rows($reportType, $data);
            $export = new class($rows, mb_substr(__('ledger_reports.sheet_'.$reportType), 0, 31)) extends LedgerRowsExport
            {
                private $sheet;

                public function __construct(array $rows, string $sheet)
                {
                    $this->rows = $rows;
                    $this->sheet = $sheet;
                }

                public function title(): string
                {
                    return $this->sheet;
                }
            };

            return Excel::download($export, $reportType.'_'.date('Y-m-d').'.xlsx');
        } catch (Exception $e) {
            Log::error('Error exporting report to Excel', ['exception' => $e->getMessage()]);

            return $this->err(route('ledger.accounting-reports.index'), __('ledger_reports.error_exporting_report'));
        }
    }

    /**
     * The rows of an export, header first (paxhitestbed's csvRows). Aging
     * exports list each contact; the documents behind the figures follow,
     * then the tie-out to the ledger.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function rows(string $reportType, array $data): array
    {
        $L = fn ($k) => __('ledger_reports.'.$k);
        $n = fn ($v) => round((float) $v, 2) + 0.0;
        $bands = ['current', 'days_31_60', 'days_61_90', 'days_91_120', 'over_120'];
        $bandHeads = [$L('days_0_30'), $L('days_31_60'), $L('days_61_90'), $L('days_91_120'), $L('over_120_days')];
        $pct = fn ($v) => round((float) $v, 1) + 0.0;

        switch ($reportType) {
            case 'receivables_aging':
            case 'payables_aging':
                $who = $reportType === 'receivables_aging' ? $L('customer') : $L('supplier');
                $rows = [array_merge([$who], $bandHeads, [$L('total')])];
                foreach ($data['details'] ?? [] as $r) {
                    $rows[] = array_merge([$r['customer_name']], array_map(fn ($b) => $n($r[$b]), $bands), [$n($r['total'])]);
                }
                $rows[] = array_merge([$L('grand_total')], array_map(fn ($b) => $n($data['summary'][$b] ?? 0), $bands), [$n($data['summary']['total'] ?? 0)]);
                $rows[] = [];
                $rows[] = [$L('owed_on_the_documents_above'), $n($data['summary']['total'] ?? 0)];
                $rows[] = [$reportType === 'receivables_aging' ? $L('balance_of_the_customer_account') : $L('balance_of_the_supplier_account'), $n($data['ledger_balance'] ?? 0)];
                $rows[] = [$L('difference'), $n($data['difference'] ?? 0)];
                $rows[] = [];
                $rows[] = [$who, $L('document'), $L('date'), $L('due'), $L('days_overdue'), $L('owed')];
                foreach ($data['details'] ?? [] as $r) {
                    foreach ($r['documents'] as $doc) {
                        $rows[] = [$r['customer_name'], $doc['number'], Carbon::parse($doc['date'])->format('d/m/Y'),
                            Carbon::parse($doc['due_date'])->format('d/m/Y'), $doc['days_overdue'], $n($doc['owed'])];
                    }
                }

                return $rows;

            case 'cash_flow':
                $rows = [[$L('section'), $L('line'), $L('amount')], ['', $L('cash_at_beginning_of_period'), $n($data['beginning_cash_balance'])]];
                foreach (['operating', 'investing', 'financing'] as $section) {
                    foreach ($data[$section.'_activities']['items'] as $item) {
                        $rows[] = [$L($section.'_activities'), $item['description'], $n($item['amount'])];
                    }
                    $rows[] = [$L($section.'_activities'), $L('net_cash_from_'.$section.'_lc'), $n($data['net_'.$section])];
                }
                $rows[] = ['', $L('net_change_in_cash'), $n($data['net_change_in_cash'])];
                $rows[] = ['', $L('cash_at_end_of_period'), $n($data['ending_cash_balance'])];

                return $rows;

            case 'comparative_cash_flow':
                $rows = [[$L('section'), $L('line'), $data['periods']['current']['name'], $data['periods']['previous']['name'], $L('change'), $L('pct_change')]];
                foreach (['operating', 'investing', 'financing'] as $section) {
                    foreach ($data[$section]['items'] as $item) {
                        $rows[] = [$L($section.'_activities'), $item['description'], $n($item['current']), $n($item['previous']), $n($item['variance']), $pct($item['change_percent'])];
                    }
                    $rows[] = [$L($section.'_activities'), $L('net_cash_from_'.$section.'_lc'), $n($data[$section]['current_total']),
                        $n($data[$section]['previous_total']), $n($data[$section]['variance']), $pct($data[$section]['change_percent'])];
                }
                $rows[] = ['', $L('net_change_in_cash'), $n($data['net_change']['current']), $n($data['net_change']['previous']),
                    $n($data['net_change']['variance']), $pct($data['net_change']['change_percent'])];

                return $rows;
        }

        return [];
    }
}
