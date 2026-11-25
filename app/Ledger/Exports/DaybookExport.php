<?php

namespace App\Ledger\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The daybook as a workbook — the month, then its summary (paxhitestbed
 * App\Exports\DaybookExport: the same two tabs, so the two files read side by side).
 */
class DaybookExport implements WithMultipleSheets
{
    use Exportable;

    protected array $book;

    protected ?array $summary;

    public function __construct(array $book, ?array $summary = null)
    {
        $this->book = $book;
        $this->summary = $summary;
    }

    public function sheets(): array
    {
        $sheets = [new DaybookMonthSheet($this->book)];
        if ($this->summary) {
            $sheets[] = new DaybookSummarySheet($this->summary + ['businessName' => $this->book['businessName'] ?? '']);
        }

        return $sheets;
    }
}
