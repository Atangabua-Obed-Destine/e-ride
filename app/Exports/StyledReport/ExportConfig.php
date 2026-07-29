<?php

namespace App\Exports\StyledReport;

class ExportConfig
{
    protected string $title = '';
    protected ?string $subtitle = null;

    /** @var array<string, scalar|null> */
    protected array $summary = [];

    /** @var array<string, scalar|null> */
    protected array $filters = [];

    /** @var list<string> */
    protected array $headings = [];

    /** @var list<list<mixed>> */
    protected array $rows = [];

    /** @var array<int, string>  index => ColumnFormat::* */
    protected array $columnFormats = [];

    /** @var array<int, string>  index => 'left'|'center'|'right' */
    protected array $columnAlignments = [];

    /** @var array<int, float>   index => width (Excel character units) */
    protected array $columnWidths = [];

    protected ?string $fileName = null;
    protected ?string $font = null;

    public static function make(): self
    {
        return new self();
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function subtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    /** @param array<string, scalar|null> $summary */
    public function summary(array $summary): self
    {
        $this->summary = $summary;
        return $this;
    }

    /** @param array<string, scalar|null> $filters */
    public function filters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    /** @param list<string> $headings */
    public function headings(array $headings): self
    {
        $this->headings = array_values($headings);
        return $this;
    }

    /** @param iterable<array<string,mixed>|list<mixed>> $rows */
    public function rows(iterable $rows): self
    {
        $out = [];
        foreach ($rows as $row) {
            if ($row instanceof \Illuminate\Contracts\Support\Arrayable) {
                $row = $row->toArray();
            }
            $out[] = is_array($row) ? array_values($row) : [$row];
        }
        $this->rows = $out;
        return $this;
    }

    /**
     * Convenience for FastExcel-style mapped data (collection of associative rows).
     * Pulls headings from the first row's keys when no headings have been set yet.
     */
    public function fromMapped(iterable $mapped): self
    {
        $rows = [];
        $first = true;
        foreach ($mapped as $row) {
            if ($row instanceof \Illuminate\Contracts\Support\Arrayable) {
                $row = $row->toArray();
            }
            if (!is_array($row)) {
                continue;
            }
            if ($first && empty($this->headings)) {
                $this->headings = array_map(static fn($k) => (string)$k, array_keys($row));
            }
            $rows[] = array_values($row);
            $first = false;
        }
        $this->rows = $rows;
        return $this;
    }

    public function columnFormat(int $index, string $format): self
    {
        $this->columnFormats[$index] = $format;
        return $this;
    }

    /** @param array<int|string, string> $formats  Either index=>format or headingName=>format */
    public function columnFormats(array $formats): self
    {
        foreach ($formats as $key => $format) {
            if (is_int($key)) {
                $this->columnFormats[$key] = $format;
                continue;
            }
            $idx = array_search($key, $this->headings, true);
            if ($idx !== false) {
                $this->columnFormats[$idx] = $format;
            }
        }
        return $this;
    }

    public function columnAlignment(int $index, string $align): self
    {
        $this->columnAlignments[$index] = $align;
        return $this;
    }

    /** @param array<int, float> $widths */
    public function columnWidths(array $widths): self
    {
        $this->columnWidths = $widths;
        return $this;
    }

    public function fileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function font(?string $font): self
    {
        $this->font = $font;
        return $this;
    }

    // ---------- Getters consumed by StyledReportExport ----------

    public function getTitle(): string { return $this->title; }
    public function getSubtitle(): ?string { return $this->subtitle; }
    /** @return array<string, scalar|null> */
    public function getSummary(): array { return $this->summary; }
    /** @return array<string, scalar|null> */
    public function getFilters(): array { return $this->filters; }
    /** @return list<string> */
    public function getHeadings(): array { return $this->headings; }
    /** @return list<list<mixed>> */
    public function getRows(): array { return $this->rows; }
    /** @return array<int, string> */
    public function getColumnFormats(): array { return $this->columnFormats; }
    /** @return array<int, string> */
    public function getColumnAlignments(): array { return $this->columnAlignments; }
    /** @return array<int, float> */
    public function getColumnWidths(): array { return $this->columnWidths; }

    public function getFileName(): string
    {
        if ($this->fileName) {
            return $this->fileName;
        }
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($this->title));
        $slug = trim((string)$slug, '-') ?: 'export';
        return $slug . '-' . time() . '.xlsx';
    }

    public function getFont(): string
    {
        return $this->font ?? Theme::font();
    }

    public function columnCount(): int
    {
        $headings = count($this->headings);
        $rowMax = 0;
        foreach ($this->rows as $row) {
            $rowMax = max($rowMax, count($row));
        }
        return max($headings, $rowMax, 1);
    }
}
