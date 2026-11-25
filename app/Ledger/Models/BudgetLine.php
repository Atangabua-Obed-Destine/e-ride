<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One line of the Income & Expenditure sheet (paxhitestbed BudgetLine).
 *
 * A budget line is a management reporting dimension, not a ledger account:
 * it can be coarser or finer than the chart. In paxhitestbed a line is reached
 * through category mappings; in PosInnova a line TARGETS a ledger account (and
 * every sub-account beneath it), optionally narrowed to one expense category
 * and/or one business location. Its actual is what the posted ledger holds on
 * that target (BudgetActualsService), so the sheet always agrees with the
 * ledger. Two levels: headings (no money of their own) and the lines under them.
 */
class BudgetLine extends Model
{
    use Auditable, BelongsToBusiness;

    public const SECTION_INCOME = 'income';
    public const SECTION_EXPENDITURE = 'expenditure';
    public const SECTION_CAPITAL = 'capital';

    public const SECTIONS = ['income', 'expenditure', 'capital'];

    /** The ledger class each section reads: income 7, expenditure 6, capital 2 (fixed assets, 20 to 27). */
    public const SECTION_CLASS = ['income' => 7, 'expenditure' => 6, 'capital' => 2];

    protected $fillable = [
        'code', 'name', 'name_fr', 'description', 'section', 'is_header', 'parent_id',
        'account_id', 'expense_category_id', 'location_id', 'is_local', 'sort_order', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'is_local' => 'boolean',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); // E-Ride: the Income & Expense categories (posinnova: POS's)
    }

    public function location()
    {
        return $this->belongsTo(\App\Ledger\ERide\BudgetDepartment::class, 'location_id'); // E-Ride: the payroll's departments (posinnova: business locations)
    }

    public function scopeActive($q)
    {
        return $q->where('status', true);
    }

    public function scopePostable($q)
    {
        return $q->where('is_header', false);
    }

    /** The name in the user's language. */
    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'fr' && $this->name_fr ? $this->name_fr : (string) $this->name;
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' — '.$this->display_name;
    }

    public static function sectionLabel(string $section): string
    {
        return __('ledger_budget.section_'.$section);
    }

    /** The whole sheet in print order (active lines), headings followed by their lines. */
    public static function sheet(): Collection
    {
        $lines = static::active()->with('account')
            ->orderByRaw("FIELD(section, 'income', 'expenditure', 'capital')")
            ->orderBy('sort_order')->orderBy('code')->get();

        return static::inTreeOrder($lines);
    }

    /**
     * Flatten lines so each heading is followed immediately by the lines filed
     * under it. A line whose heading is retired or missing is kept, at the top
     * level of its section: dropping it would quietly remove money from the sheet.
     */
    public static function inTreeOrder($lines): Collection
    {
        $ids = $lines->pluck('id')->all();

        $childrenOf = $lines
            ->filter(fn (self $l) => $l->parent_id && in_array($l->parent_id, $ids, true))
            ->sortBy([['sort_order', 'asc'], ['code', 'asc']])
            ->groupBy('parent_id');

        $ordered = collect();
        foreach (self::SECTIONS as $section) {
            $top = $lines->where('section', $section)
                ->filter(fn (self $l) => ! $l->parent_id || ! in_array($l->parent_id, $ids, true))
                ->sortBy([['sort_order', 'asc'], ['code', 'asc']]);
            foreach ($top as $line) {
                $ordered->push($line);
                foreach ($childrenOf->get($line->id, collect()) as $child) {
                    $ordered->push($child);
                }
            }
        }

        return $ordered->values();
    }

    /**
     * Where each heading's group ends, as [last child line id => heading], so the
     * screen, the PDF and the workbook draw the subtotals in the same places.
     */
    public static function groupEnds($orderedLines): array
    {
        $ends = [];
        $current = null;
        $last = null;
        foreach ($orderedLines as $line) {
            if ($line->is_header) {
                if ($current && $last) {
                    $ends[$last] = $current;
                }
                $current = $line;
                $last = null;
                continue;
            }
            if ($current && $line->parent_id === $current->id) {
                $last = $line->id;
                continue;
            }
            if ($current && $last) {
                $ends[$last] = $current;
            }
            $current = null;
            $last = null;
        }
        if ($current && $last) {
            $ends[$last] = $current;
        }

        return $ends;
    }

    public function getAuditDescription($event)
    {
        return "Budget line {$event}: {$this->code} {$this->name} ({$this->section})";
    }
}
