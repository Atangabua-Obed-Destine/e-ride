<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Exports\BalanceSheetExport;
use App\Ledger\Exports\IncomeStatementExport;
use App\Ledger\Exports\TrialBalanceExport;
use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\JournalEntryLine;
use App\Ledger\Services\FinancialStatements;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * General ledger and the statements: paxhitestbed's
 * Admin\GeneralLedgerController, same screens, filters and exports.
 *
 * Every figure is read from posted, non-deleted journal entries of the current
 * business (FinancialStatements, the scoped models), never from the balance
 * stored on the account. paxhitestbed's school-only income statement (fees,
 * payroll, the use_journal_entries=0 module view) is not ported: the income
 * statement here is its journal-based one.
 */
class GeneralLedgerController extends LedgerController
{
    /** paxhitestbed's class names (by-class screen). */
    private const CLASS_NAMES = [
        1 => ['en' => 'Resource Accounts (Capitaux)', 'fr' => 'Comptes de Capitaux'],
        2 => ['en' => 'Fixed Assets (Immobilisations)', 'fr' => "Comptes d'Immobilisations"],
        3 => ['en' => 'Inventory and In-progress (Stocks)', 'fr' => 'Comptes de Stocks'],
        4 => ['en' => 'Third Party Accounts (Tiers)', 'fr' => 'Comptes de Tiers'],
        5 => ['en' => 'Financial Accounts (Trésorerie)', 'fr' => 'Comptes de Trésorerie'],
        6 => ['en' => 'Expense Accounts (Charges)', 'fr' => 'Comptes de Charges'],
        7 => ['en' => 'Revenue Accounts (Produits)', 'fr' => 'Comptes de Produits'],
        8 => ['en' => 'Off-Balance Sheet Accounts', 'fr' => 'Comptes Spéciaux'],
    ];

    private function statements(): FinancialStatements
    {
        return app(FinancialStatements::class);
    }

    /** A date from the query string (Y-m-d), or null. */
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

    /**
     * Display general ledger index
     */
    public function index()
    {
        $this->allow('general-ledger-view');

        $data['title'] = __('ledger_gl.general_ledger');
        $data['accounts'] = ChartOfAccount::where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();
        $data['activeFiscalYear'] = FiscalYear::getActiveFiscalYear();

        return view('ledger.general-ledger.index', $data);
    }

    /**
     * Display ledger for a specific account
     */
    public function account(Request $request, $accountId)
    {
        $this->allow('general-ledger-view');

        return view('ledger.general-ledger.account', $this->accountData($request, $accountId));
    }

    private function accountData(Request $request, $accountId): array
    {
        $data['title'] = __('ledger_gl.account_ledger');
        $data['account'] = ChartOfAccount::with('parent')->findOrFail($accountId);

        $startDate = $this->date($request, 'start_date');
        $endDate = $this->date($request, 'end_date');
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;

        // Opening balance: all posted entries before the start date
        $openingBalance = $startDate ? $this->calculateOpeningBalance($data['account'], $startDate) : (float) $data['account']->opening_balance;
        $data['openingBalance'] = $openingBalance;

        $data['transactions'] = JournalEntryLine::with(['journalEntry.fiscalYear', 'journalEntry.creator'])
            ->where('journal_entry_lines.account_id', $data['account']->id)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->where('is_posted', true);
                if ($startDate) {
                    $q->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('entry_date', '<=', $endDate);
                }
            })
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.entry_number')
            ->orderBy('journal_entry_lines.id')
            ->select('journal_entry_lines.*')
            ->get();

        $debit = (float) $data['transactions']->sum('debit');
        $credit = (float) $data['transactions']->sum('credit');
        $data['closingBalance'] = $openingBalance + ($data['account']->normal_balance === 'debit' ? $debit - $credit : $credit - $debit);

        return $data;
    }

    /**
     * Display ledger for all accounts by class
     */
    public function byClass(Request $request, $classNumber)
    {
        $this->allow('general-ledger-view');
        if (! ctype_digit((string) $classNumber) || (int) $classNumber < 1 || (int) $classNumber > 9) {
            abort(404);
        }
        $classNumber = (int) $classNumber;

        $data['title'] = __('ledger_gl.accounts_by_class');
        $data['classNumber'] = $classNumber;
        $data['className'] = isset(self::CLASS_NAMES[$classNumber]) ? __('ledger_gl.class_name_'.$classNumber) : __('ledger_gl.class').' '.$classNumber;
        $data['className_fr'] = self::CLASS_NAMES[$classNumber]['fr'] ?? 'Classe '.$classNumber;

        $startDate = $this->date($request, 'start_date');
        $endDate = $this->date($request, 'end_date');
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;

        // Every account of the class (headings and totals included)
        $data['accounts'] = ChartOfAccount::where('account_code', 'LIKE', $classNumber.'%')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        // Movements of each account that can carry money: a detail account, or
        // a heading that was posted to anyway.
        $balances = $this->statements()->accounts($startDate, $endDate)->keyBy('id');
        $data['accounts']->transform(function ($account) use ($balances) {
            $b = $balances->get($account->id);
            if ($b && ($account->account_category === 'detail' || $b->debit > 0 || $b->credit > 0 || abs($b->opening) > 0.004)) {
                $account->period_opening = $b->opening;
                $account->total_debit = $b->debit;
                $account->total_credit = $b->credit;
            } else {
                $account->period_opening = 0;
                $account->total_debit = 0;
                $account->total_credit = 0;
            }

            return $account;
        });

        return view('ledger.general-ledger.by-class', $data);
    }

    /**
     * Display trial balance
     */
    public function trialBalance(Request $request)
    {
        $this->allow('trial-balance-view');

        return view('ledger.general-ledger.trial-balance', $this->trialBalanceData($request));
    }

    private function trialBalanceData(Request $request): array
    {
        $data['title'] = __('ledger_gl.trial_balance');

        $startDate = $this->date($request, 'start_date');
        $endDate = $this->date($request, 'end_date');

        // paxhitestbed shows the fiscal year filter but never applied it; here
        // a year chosen with no dates gives that year's dates.
        $year = $request->input('fiscal_year_id') ? FiscalYear::find($request->input('fiscal_year_id')) : null;
        if ($year && ! $startDate && ! $endDate) {
            $startDate = $year->start_date->toDateString();
            $endDate = $year->end_date->toDateString();
        }
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;

        // Every account that carries money, each with its opening (before the
        // period, in the direction of its normal balance) and its movements.
        $data['accounts'] = $this->statements()->accounts($startDate, $endDate)
            ->map(function ($a) {
                $a->opening_balance = $a->normal_balance === 'debit' ? $a->opening : -$a->opening;
                $a->period_debit = $a->debit;
                $a->period_credit = $a->credit;

                return $a;
            })
            ->filter(fn ($a) => abs($a->opening_balance) > 0.004 || $a->period_debit != 0 || $a->period_credit != 0)
            ->values();

        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();

        return $data;
    }

    /**
     * Opening balance of an account before a date, in the direction of its
     * normal balance: the stored opening balance plus posted entries before.
     */
    private function calculateOpeningBalance(ChartOfAccount $account, $beforeDate): float
    {
        $lines = JournalEntryLine::where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($beforeDate) {
                $q->where('is_posted', true)->where('entry_date', '<', $beforeDate);
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $totalDebit = (float) ($lines->total_debit ?? 0);
        $totalCredit = (float) ($lines->total_credit ?? 0);

        return $account->normal_balance === 'debit'
            ? (float) $account->opening_balance + $totalDebit - $totalCredit
            : (float) $account->opening_balance + $totalCredit - $totalDebit;
    }

    /**
     * Export account ledger to PDF: the same figures as the account screen, on paper.
     */
    public function exportAccountPDF(Request $request, $accountId)
    {
        $this->allow('general-ledger-export');
        $data = $this->accountData($request, $accountId);

        return $this->pdf('ledger.general-ledger.account-pdf', $data, 'L',
            'ledger-'.$data['account']->account_code.'-'.date('Y-m-d').'.pdf');
    }

    /**
     * Export trial balance to Excel, read from the trial balance screen itself
     * so the file and the screen cannot differ.
     */
    public function exportTrialBalanceExcel(Request $request)
    {
        $this->allow('trial-balance-export');
        $data = $this->trialBalanceData($request);

        return Excel::download(new TrialBalanceExport($data['accounts']),
            'trial-balance-'.($data['endDate'] ?: date('Y-m-d')).'.xlsx');
    }

    /**
     * Display Balance Sheet (OHADA format)
     */
    public function balanceSheet(Request $request)
    {
        $this->allow('balance-sheet-view');
        $asOfDate = $this->date($request, 'as_of_date', date('Y-m-d'));
        $comparativeDate = $this->date($request, 'comparative_date');

        $data = $this->getBalanceSheetData($asOfDate, $comparativeDate);
        $data['title'] = __('ledger_gl.balance_sheet');
        $data['asOfDate'] = $asOfDate;
        $data['comparativeDate'] = $comparativeDate;
        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();

        return view('ledger.general-ledger.balance-sheet', $data);
    }

    /**
     * Display Income Statement (Compte de Résultat) from posted journal entries
     */
    public function incomeStatement(Request $request)
    {
        $this->allow('income-statement-view');
        $startDate = $this->date($request, 'start_date');
        $endDate = $this->date($request, 'end_date', date('Y-m-d'));

        $data = $this->statements()->incomeStatement($startDate, $endDate);
        $data['title'] = __('ledger_gl.income_statement');
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;
        // the screen lists the biggest amounts first (paxhitestbed)
        $data['revenueByAccount'] = $data['revenue']->sortByDesc('amount')->values();
        $data['expensesByAccount'] = $data['expenses']->sortByDesc('amount')->values();
        $data['otherResultsByAccount'] = $data['otherResults']->sortByDesc('amount')->values();
        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();

        return view('ledger.general-ledger.income-statement', $data);
    }

    /**
     * Export Balance Sheet to PDF
     */
    public function exportBalanceSheetPDF(Request $request)
    {
        $this->allow('balance-sheet-export');
        $asOfDate = $this->date($request, 'as_of_date', date('Y-m-d'));
        $comparativeDate = $this->date($request, 'comparative_date');

        $data = $this->getBalanceSheetData($asOfDate, $comparativeDate);
        $data['asOfDate'] = $asOfDate;
        $data['comparativeDate'] = $comparativeDate;

        return $this->pdf('ledger.general-ledger.balance-sheet-pdf', $data, 'P', 'balance-sheet-'.$asOfDate.'.pdf');
    }

    /**
     * Export Balance Sheet to Excel
     */
    public function exportBalanceSheetExcel(Request $request)
    {
        $this->allow('balance-sheet-export');
        $asOfDate = $this->date($request, 'as_of_date', date('Y-m-d'));
        $comparativeDate = $this->date($request, 'comparative_date');
        $data = $this->getBalanceSheetData($asOfDate, $comparativeDate);

        return Excel::download(new BalanceSheetExport($data, $asOfDate, $comparativeDate), 'balance-sheet-'.$asOfDate.'.xlsx');
    }

    /**
     * Export Income Statement to PDF
     */
    public function exportIncomeStatementPDF(Request $request)
    {
        $this->allow('income-statement-export');
        $startDate = $this->date($request, 'start_date');
        $endDate = $this->date($request, 'end_date', date('Y-m-d'));

        $data = $this->statements()->incomeStatement($startDate, $endDate);
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;

        return $this->pdf('ledger.general-ledger.income-statement-pdf', $data, 'P', 'income-statement-'.$endDate.'.pdf');
    }

    /**
     * Export Income Statement to Excel
     */
    public function exportIncomeStatementExcel(Request $request)
    {
        $this->allow('income-statement-export');
        $startDate = $this->date($request, 'start_date');
        $endDate = $this->date($request, 'end_date', date('Y-m-d'));
        $data = $this->statements()->incomeStatement($startDate, $endDate);

        return Excel::download(new IncomeStatementExport($data), 'income-statement-'.$endDate.'.xlsx');
    }

    private function getBalanceSheetData(string $asOfDate, ?string $comparativeDate = null): array
    {
        $data = $this->statements()->balanceSheet($asOfDate);
        $data['comparative'] = $comparativeDate ? $this->statements()->balanceSheet($comparativeDate) : null;

        return $data;
    }

    /** Render a view to PDF with mpdf (as PosInnova's other reports do) and send it as a download. */
    private function pdf(string $view, array $data, string $orientation, string $filename)
    {
        $data['businessName'] = optional(session('business'))->name
            ?: \DB::table('ledger_businesses')->where('id', Ledger::businessId())->value('name');
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
