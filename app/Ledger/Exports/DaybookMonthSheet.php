<?php

namespace App\Ledger\Exports;

use App\Ledger\Models\FiscalYear;
use App\Ledger\Services\Preview;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * One month of the cash analysis book (paxhitestbed App\Exports\DaybookMonthSheet).
 * Amounts are real numbers, not formatted strings, so the file can be re-totalled.
 * (paxhitestbed writes this sheet's words in English only; here they follow the user's language.)
 */
class DaybookMonthSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents, WithStrictNullComparison
{
    protected array $data;

    protected int $headingRow = 0;

    protected int $lastRow = 0;

    protected array $totalRows = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        // Excel forbids : \ / ? * [ ] in sheet names and caps them at 31 characters.
        return mb_substr(preg_replace('~[:\\\\/?*\[\]]~', '-', FiscalYear::periodLabel($this->data['period'])), 0, 31);
    }

    public function array(): array
    {
        $period = FiscalYear::periodLabel($this->data['period']);
        $lines = $this->data['lines'];
        $acc = fn ($id) => isset($lines[$id]) ? $lines[$id]->account_code.' '.Preview::accountName($lines[$id]) : '#'.$id;
        $account = $this->data['account'];

        $rows = [];
        $rows[] = [$this->data['businessName'] ?? ''];
        $rows[] = [mb_strtoupper(__('ledger_daybook.pdf_title'))];
        $rows[] = [$period.'   |   '.__('ledger_daybook.fiscal_year').' '.$this->data['year']->name.'   |   '.($account ? $account->label : __('ledger_daybook.all_cash_boxes'))];
        $rows[] = [__('ledger_daybook.generated').' '.now()->format('d/m/Y H:i').'   |   '.__('ledger_daybook.amounts_in', ['currency' => session('currency')['symbol'] ?? 'FCFA'])];
        $rows[] = [''];

        $this->headingRow = count($rows) + 1;
        $rows[] = [__('ledger_daybook.date'), __('ledger_daybook.ref'), __('ledger_daybook.description'), __('ledger_daybook.cash_box'),
            __('ledger_daybook.analysis_column'), __('ledger_daybook.debit_in'), __('ledger_daybook.credit_out')];

        $rows[] = ['', '', __('ledger_daybook.opening_balance'), '', '', (float) $this->data['opening'], null];
        foreach ($this->data['rows'] as $row) {
            $rows[] = [
                \Carbon\Carbon::parse($row['date'])->format('d/m/Y'),
                $row['ref'],
                $row['description'].' ('.__('ledger_daybook.src_'.$row['source']).($row['is_reversal'] ? ', '.__('ledger_daybook.reversal') : '').')',
                $acc($row['cash_account_id']),
                $row['unanalysed'] ? mb_strtoupper(__('ledger_daybook.unanalysed')) : $acc($row['line_id']),
                $row['direction'] === 'in' ? (float) $row['amount'] : null,
                $row['direction'] === 'out' ? (float) $row['amount'] : null,
            ];
        }

        $rows[] = ['', '', '', '', __('ledger_daybook.total_for').' '.mb_strtoupper($period), (float) $this->data['totalIn'], (float) $this->data['totalOut']];
        $this->totalRows[] = count($rows);
        $rows[] = ['', '', '', '', __('ledger_daybook.net_movement_caps'), round((float) $this->data['totalIn'] - (float) $this->data['totalOut'], 2), null];
        $this->totalRows[] = count($rows);
        $rows[] = ['', '', '', '', __('ledger_daybook.closing_balance'), (float) $this->data['closing'], null];
        $this->totalRows[] = count($rows);
        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 16, 'C' => 52, 'D' => 26, 'E' => 34, 'F' => 14, 'G' => 14];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max($this->lastRow, $this->headingRow);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $h = "A{$this->headingRow}:G{$this->headingRow}";
                $sheet->getStyle($h)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($h)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('34495E');
                // Numbers as numbers, with a dash where nothing moved.
                $sheet->getStyle('F'.($this->headingRow + 1).':G'.$last)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);"—"');
                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('34495E');
                }
                $sheet->freezePane('D'.($this->headingRow + 1));
            },
        ];
    }
}
