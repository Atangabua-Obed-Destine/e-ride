<?php

namespace App\Exports\StyledReport;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StyledReportExport implements WithEvents
{
    use Exportable;

    public function __construct(protected ExportConfig $config)
    {
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->render($event->sheet->getDelegate());
            },
        ];
    }

    protected function render(Worksheet $sheet): void
    {
        $config = $this->config;
        // Ensure a reasonable minimum width so the meta block (label + value)
        // and table header always have room to render even when there are no
        // headings or rows yet.
        $columnCount = max($config->columnCount(), 3);
        $lastColLetter = Coordinate::stringFromColumnIndex($columnCount);
        $font = $config->getFont();

        $sheet->getParent()->getDefaultStyle()->getFont()->setName($font)->setSize(Theme::BODY_PT);
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);

        $row = 1;

        // ---------------- TITLE ----------------
        $titleRange = "A{$row}:{$lastColLetter}{$row}";
        $sheet->mergeCells($titleRange);
        $sheet->setCellValue("A{$row}", $config->getTitle());
        $sheet->getRowDimension($row)->setRowHeight(Theme::TITLE_HEIGHT);
        $sheet->getStyle($titleRange)->applyFromArray([
            'font' => [
                'name' => $font,
                'bold' => true,
                'size' => Theme::TITLE_PT,
                'color' => ['argb' => 'FF' . Theme::TITLE_TEXT],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'right' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFBFBFBF'],
                ],
            ],
        ]);
        $row++;

        if ($config->getSubtitle()) {
            $subRange = "A{$row}:{$lastColLetter}{$row}";
            $sheet->mergeCells($subRange);
            $sheet->setCellValue("A{$row}", $config->getSubtitle());
            $sheet->getRowDimension($row)->setRowHeight(24);
            $sheet->getStyle($subRange)->applyFromArray([
                'font' => ['name' => $font, 'italic' => true, 'size' => 12, 'color' => ['argb' => 'FF555555']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $row++;
        }

        // ---------------- SUMMARY ----------------
        if (!empty($config->getSummary())) {
            $row = $this->renderMetaBlock(
                $sheet,
                $row,
                $columnCount,
                'Analytics',
                $config->getSummary()
            );
        }

        // ---------------- FILTERS ----------------
        if (!empty($config->getFilters())) {
            $row = $this->renderMetaBlock(
                $sheet,
                $row,
                $columnCount,
                'Filter Criteria',
                $config->getFilters()
            );
        }

        // spacer row before table
        $sheet->getRowDimension($row)->setRowHeight(8);
        $row++;

        $headerRow = $row;

        // ---------------- TABLE HEADER ----------------
        if (!empty($config->getHeadings())) {
            $col = 1;
            foreach ($config->getHeadings() as $heading) {
                $sheet->setCellValueByColumnAndRow($col, $headerRow, $heading);
                $col++;
            }
            $headerRange = "A{$headerRow}:{$lastColLetter}{$headerRow}";
            $sheet->getRowDimension($headerRow)->setRowHeight(Theme::HEADER_HEIGHT);
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'name'  => $font,
                    'bold'  => true,
                    'size'  => Theme::HEADER_PT,
                    'color' => ['argb' => 'FF' . Theme::HEADER_TEXT],
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF' . Theme::headerFill()],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF' . Theme::BORDER],
                    ],
                ],
            ]);
            $row++;
        }

        // ---------------- TABLE BODY ----------------
        $columnFormats = $config->getColumnFormats();
        $bodyStartRow = $row;
        $hasBodyRows = false;
        $numericFormats = [ColumnFormat::CURRENCY, ColumnFormat::DECIMAL, ColumnFormat::INTEGER, ColumnFormat::PERCENTAGE];
        $dateFormats    = [ColumnFormat::DATE, ColumnFormat::DATETIME];
        foreach ($config->getRows() as $rowData) {
            $hasBodyRows = true;
            $col = 1;
            foreach ($rowData as $value) {
                $format = $columnFormats[$col - 1] ?? ColumnFormat::TEXT;
                $coerced = $this->coerceForFormat($value, $format);

                if (in_array($format, $numericFormats, true) && is_numeric($coerced)) {
                    $sheet->setCellValueExplicitByColumnAndRow($col, $row, (float)$coerced, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } elseif (in_array($format, $dateFormats, true) && is_numeric($coerced)) {
                    // Excel serial date — write as numeric, format code applies the visual date format
                    $sheet->setCellValueExplicitByColumnAndRow($col, $row, (float)$coerced, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } elseif ($coerced === null || $coerced === '') {
                    $sheet->setCellValueByColumnAndRow($col, $row, '');
                } elseif (is_string($coerced)) {
                    // Force string type to preserve leading '+', leading zeros, phone numbers, IDs, etc.
                    $sheet->setCellValueExplicitByColumnAndRow($col, $row, $coerced, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValueByColumnAndRow($col, $row, $this->normalizeValue($coerced));
                }
                $col++;
            }
            $sheet->getRowDimension($row)->setRowHeight(Theme::BODY_HEIGHT);
            $row++;
        }
        $bodyEndRow = $row - 1;

        // Empty-state row: render a single merged "No records found" line so the
        // file always has a visible body section under the table header.
        if ($bodyEndRow < $bodyStartRow) {
            $emptyRange = "A{$row}:{$lastColLetter}{$row}";
            if ($columnCount > 1) {
                $sheet->mergeCells($emptyRange);
            }
            $sheet->setCellValue("A{$row}", translate('No records found'));
            $sheet->getRowDimension($row)->setRowHeight(Theme::BODY_HEIGHT);
            $sheet->getStyle($emptyRange)->applyFromArray([
                'font' => ['italic' => true, 'size' => Theme::BODY_PT, 'color' => ['argb' => 'FF888888']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']]],
            ]);
            $bodyEndRow = $row;
            $row++;
        }

        if ($bodyEndRow >= $bodyStartRow) {
            $bodyRange = "A{$bodyStartRow}:{$lastColLetter}{$bodyEndRow}";
            $sheet->getStyle($bodyRange)->applyFromArray([
                'font' => ['name' => $font, 'size' => Theme::BODY_PT],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFBFBFBF'],
                    ],
                ],
            ]);
        }

        // ---------------- COLUMN FORMATS / ALIGNMENTS ----------------
        $columnAlignments = $config->getColumnAlignments();
        for ($i = 0; $i < $columnCount; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            $bodyColRange = "{$colLetter}{$bodyStartRow}:{$colLetter}{$bodyEndRow}";
            $format = $columnFormats[$i] ?? ColumnFormat::TEXT;
            $align  = $columnAlignments[$i] ?? Theme::alignmentFor($format);

            if ($hasBodyRows && $bodyEndRow >= $bodyStartRow) {
                $formatCode = Theme::formatCodeFor($format);
                if ($formatCode) {
                    $sheet->getStyle($bodyColRange)->getNumberFormat()->setFormatCode($formatCode);
                }
                $sheet->getStyle($bodyColRange)->getAlignment()->setHorizontal($this->phpSpreadsheetAlign($align));
            }
        }

        // ---------------- COLUMN WIDTHS ----------------
        $widths = $config->getColumnWidths();
        for ($i = 0; $i < $columnCount; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            if (isset($widths[$i])) {
                $sheet->getColumnDimension($colLetter)->setWidth((float)$widths[$i]);
                continue;
            }
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // freeze header row so long lists stay readable
        if ($hasBodyRows && $bodyEndRow >= $bodyStartRow) {
            $sheet->freezePane("A" . ($headerRow + 1));
        }
    }

    protected function renderMetaBlock(Worksheet $sheet, int $row, int $columnCount, string $label, array $entries): int
    {
        $lastColLetter = Coordinate::stringFromColumnIndex($columnCount);

        // Reserve at least 1 column for the value side; clamp so we never produce
        // an inverted range like "C2:A2" on narrow sheets.
        $labelSpan = max(1, (int)floor($columnCount / 5));
        $labelSpan = min($labelSpan, max(1, $columnCount - 1));
        $labelEnd  = Coordinate::stringFromColumnIndex($labelSpan);
        $valueStart = Coordinate::stringFromColumnIndex($labelSpan + 1);

        $labelRange = "A{$row}:{$labelEnd}{$row}";
        $valueRange = "{$valueStart}{$row}:{$lastColLetter}{$row}";

        if ($labelSpan > 1) {
            $sheet->mergeCells($labelRange);
        }
        if ($valueStart !== $lastColLetter) {
            $sheet->mergeCells($valueRange);
        }
        $sheet->setCellValue("A{$row}", translate($label));
        $sheet->setCellValue("{$valueStart}{$row}", $this->joinEntries($entries));
        $sheet->getRowDimension($row)->setRowHeight(Theme::META_HEIGHT);

        $sheet->getStyle($labelRange)->applyFromArray([
            'font' => ['bold' => true, 'size' => Theme::META_PT, 'color' => ['argb' => 'FF1F1F1F']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . Theme::META_LABEL_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']]],
        ]);
        $sheet->getStyle($valueRange)->applyFromArray([
            'font' => ['size' => Theme::META_PT, 'color' => ['argb' => 'FF1F1F1F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'indent' => 1],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']]],
        ]);
        return $row + 1;
    }

    protected function joinEntries(array $entries): string
    {
        $parts = [];
        foreach ($entries as $k => $v) {
            $val = ($v === null || $v === '') ? translate('N/A') : (is_scalar($v) ? (string)$v : json_encode($v));
            $parts[] = $k . ': ' . $val;
        }
        return implode('   |   ', $parts);
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($value);
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        return $value;
    }

    /**
     * Best-effort coercion so that pre-formatted strings (e.g. "$ 1,500.00",
     * "৳ 1500", "1,500 €") still land as numeric values in numeric columns —
     * preserving SUM/AVG/MIN/MAX in Excel. Date strings are passed through to
     * setCellValue which then renders via the column number-format code.
     */
    protected function coerceForFormat(mixed $value, string $format): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (in_array($format, [ColumnFormat::CURRENCY, ColumnFormat::DECIMAL, ColumnFormat::PERCENTAGE], true)) {
            if (is_numeric($value)) {
                return $value + 0;
            }
            if (is_string($value)) {
                $stripped = preg_replace('/[^0-9.\-]/u', '', str_replace(',', '', $value));
                return $stripped === '' || $stripped === '-' || $stripped === '.' ? null : (float)$stripped;
            }
        }
        if ($format === ColumnFormat::INTEGER) {
            if (is_numeric($value)) {
                return (int)$value;
            }
            if (is_string($value)) {
                $stripped = preg_replace('/[^0-9\-]/u', '', $value);
                return $stripped === '' || $stripped === '-' ? null : (int)$stripped;
            }
        }
        if ($format === ColumnFormat::DATE || $format === ColumnFormat::DATETIME) {
            if ($value instanceof \DateTimeInterface) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($value);
            }
            if (is_string($value)) {
                $ts = strtotime($value);
                if ($ts !== false) {
                    return \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel((new \DateTime())->setTimestamp($ts));
                }
            }
        }
        return $value;
    }

    protected function phpSpreadsheetAlign(string $align): string
    {
        return match ($align) {
            'right'  => Alignment::HORIZONTAL_RIGHT,
            'center' => Alignment::HORIZONTAL_CENTER,
            default  => Alignment::HORIZONTAL_LEFT,
        };
    }
}
