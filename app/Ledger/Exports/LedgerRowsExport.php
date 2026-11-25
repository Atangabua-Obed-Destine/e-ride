<?php

namespace App\Ledger\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A statement as rows: the first row is the heading (bold), amounts are plain
 * numbers rounded to 2 decimals so the spreadsheet can add them up. Same
 * columns as paxhitestbed's CSV files for the same statement.
 */
abstract class LedgerRowsExport implements FromArray, ShouldAutoSize, WithStrictNullComparison, WithStyles, WithTitle
{
    /** @var array<int, array<int, mixed>> */
    protected $rows = [];

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    protected static function amount($v): float
    {
        return round((float) $v, 2) + 0.0;
    }
}
