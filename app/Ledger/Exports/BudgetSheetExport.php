<?php

namespace App\Ledger\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The Income & Expenditure sheet as a workbook (paxhitestbed
 * BudgetSheetExport): the copy to work in, figures as numbers, with the same
 * section totals and group subtotals as the screen, the reconciliation to
 * cash, and the agreement with the general ledger.
 */
class BudgetSheetExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    protected $data;

    protected $sectionRows = [];

    protected $headerRows = [];

    protected $totalRows = [];

    protected $subtotalRows = [];

    protected $headingRow = 0;

    protected $lastRow = 0;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        $name = preg_replace('~[:\\\\/?*\[\]]~', '-', (string) $this->data['budget']->title);

        return mb_substr(__('ledger_budget.budget').' '.$name, 0, 31);
    }

    public function array(): array
    {
        $budget = $this->data['budget'];
        $totals = $this->data['totals'];
        $previous = $this->data['previous'];
        $priorTotals = $this->data['priorTotals'];
        $rows = [];
        $rows[] = [$this->data['businessName'] ?? config('app.name')];
        $rows[] = [mb_strtoupper(__('ledger_budget.pdf_heading'))];
        $rows[] = [$budget->title.'   |   '.$budget->start_date->format('d/m/Y').' - '.$budget->end_date->format('d/m/Y')
            .'   |   '.__('ledger_budget.status').': '.\App\Ledger\Models\Budget::statusLabel($budget->status)];
        $rows[] = [__('ledger_budget.generated_on', ['date' => now()->format('d/m/Y H:i')])];
        $rows[] = [''];
        $this->headingRow = count($rows) + 1;
        $rows[] = [__('ledger_budget.code'), __('ledger_budget.description'), __('ledger_budget.posts_to'),
            $previous ? $previous->title : __('ledger_budget.last_year'), __('ledger_budget.budget'), __('ledger_budget.actual'), __('ledger_budget.variance')];
        $rows[] = $this->line('', __('ledger_budget.opening_balance_caps'), '', $priorTotals['opening'], $budget->opening_balance, $totals['actual']['opening'], null);
        $this->totalRows[] = count($rows);

        $current = null;
        foreach ($this->data['lines'] as $line) {
            if ($line->section !== $current) {
                $this->appendSectionTotals($rows, $current, $line->section);
                $current = $line->section;
                $rows[] = [mb_strtoupper(__('ledger_budget.sheet_section_'.$current)), '', '', '', '', '', ''];
                $this->sectionRows[] = count($rows);
            }
            $b = $this->data['budgeted'][$line->id] ?? 0;
            $a = $this->data['actual'][$line->id] ?? 0;
            $p = $this->data['priorActual'][$line->id] ?? 0;
            $rows[] = $this->line($line->code, ($line->is_header ? '' : '    ').$line->display_name,
                implode(' · ', $this->data['accountsByLine'][$line->id] ?? []), $p, $b, $a, $b ? $a - $b : null);
            if ($line->is_header) {
                $this->headerRows[] = count($rows);
            }
            $end = $this->data['groupEnds'][$line->id] ?? null;
            if ($end) {
                $gb = $this->data['budgeted'][$end->id] ?? 0;
                $ga = $this->data['actual'][$end->id] ?? 0;
                $rows[] = $this->line('', '  '.__('ledger_budget.total').' '.$end->display_name, '', $this->data['priorActual'][$end->id] ?? 0, $gb, $ga, $gb ? $ga - $gb : null);
                $this->subtotalRows[] = count($rows);
            }
        }
        $this->appendSectionTotals($rows, $current, null);

        $rows[] = [''];
        $rows[] = [mb_strtoupper(__('ledger_budget.cash_position'))];
        $this->headerRows[] = count($rows);
        $rows[] = ['', __('ledger_budget.closing_as_presented'), '', '', '', (float) $totals['actual']['closing'], null];
        $rows[] = ['', __('ledger_budget.add_back_depreciation'), '', '', '', (float) $totals['actual']['depreciation'], null];
        $rows[] = ['', __('ledger_budget.less_capital'), '', '', '', -(float) $totals['actual']['capital'], null];
        $rows[] = ['', __('ledger_budget.expected_cash'), '', '', '', (float) $totals['actual']['cash'], null];
        $this->totalRows[] = count($rows);

        $recon = $this->data['reconciliation'] ?? null;
        if ($recon) {
            $rows[] = [''];
            $rows[] = [mb_strtoupper(__('ledger_budget.agreement_with_accounts'))];
            $this->headerRows[] = count($rows);
            $rows[] = ['', __('ledger_budget.section'), __('ledger_budget.ledger_class'), null, __('ledger_budget.this_sheet'), __('ledger_budget.ledger'), __('ledger_budget.difference')];
            $this->headerRows[] = count($rows);
            foreach ($recon['sections'] as $s) {
                $rows[] = ['', $s['label'], __('ledger_budget.syscohada_class', ['class' => $s['class']]), null, (float) $s['sheet'], (float) $s['ledger'], $s['difference'] == 0 ? null : (float) $s['difference']];
            }
            foreach ($recon['issues'] as $issue) {
                $rows[] = ['', __('ledger_budget.issue'), $issue['detail']];
            }
            foreach ($recon['known_differences'] as $k) {
                $rows[] = ['', __('ledger_budget.known'), $k['detail']];
            }
        }
        $this->lastRow = count($rows);

        return $rows;
    }

    protected function line($code, $name, $account, $prior, $budget, $actual, $variance): array
    {
        $n = fn ($v) => ($v === null || (float) $v == 0) ? null : round((float) $v, 2);

        return [$code, $name, $account, $n($prior), $n($budget), $n($actual), $n($variance)];
    }

    protected function appendSectionTotals(array &$rows, ?string $ending, ?string $starting): void
    {
        $t = $this->data['totals'];
        $p = $this->data['priorTotals'];
        if ($ending === 'income' && $starting !== 'income') {
            $rows[] = $this->line('', __('ledger_budget.total_income_caps'), '', $p['income'], $t['budget']['income'], $t['actual']['income'], $t['actual']['income'] - $t['budget']['income']);
            $this->totalRows[] = count($rows);
        }
        if ($ending === 'expenditure' && $starting !== 'expenditure') {
            $rows[] = $this->line('', __('ledger_budget.total_expenditure_caps'), '', $p['expenditure'], $t['budget']['expenditure'], $t['actual']['expenditure'], $t['actual']['expenditure'] - $t['budget']['expenditure']);
            $this->totalRows[] = count($rows);
            $rows[] = $this->line('', __('ledger_budget.closing_balance_caps'), '', $p['closing'], $t['budget']['closing'], $t['actual']['closing'], null);
            $this->totalRows[] = count($rows);
        }
        if ($ending === 'capital' && $starting !== 'capital') {
            $rows[] = $this->line('', __('ledger_budget.total_capital_caps'), '', $p['capital'], $t['budget']['capital'], $t['actual']['capital'], $t['actual']['capital'] - $t['budget']['capital']);
            $this->totalRows[] = count($rows);
        }
    }

    public function columnWidths(): array
    {
        return ['A' => 9, 'B' => 46, 'C' => 34, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max($this->lastRow, $this->headingRow);
                foreach (['A1:G1', 'A2:G2', 'A3:G3', 'A4:G4'] as $r) {
                    $sheet->mergeCells($r);
                }
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A3:A4')->getFont()->setSize(9)->getColor()->setRGB('55636B');
                $sheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $head = 'A'.$this->headingRow.':G'.$this->headingRow;
                $sheet->getStyle($head)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($head)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('34495E');
                $sheet->getStyle($head)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D'.($this->headingRow + 1).':G'.$last)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);"—"');
                foreach ($this->sectionRows as $row) {
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('34495E');
                }
                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF1F6');
                }
                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('34495E');
                }
                foreach ($this->subtotalRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true)->setItalic(true);
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7F9FB');
                }
                $sheet->getStyle('C'.$this->headingRow.':C'.$last)->getFont()->setSize(8)->getColor()->setRGB('8A969C');
                $sheet->freezePane('D'.($this->headingRow + 1));
                $sheet->setSelectedCell('A1');
            },
        ];
    }
}
