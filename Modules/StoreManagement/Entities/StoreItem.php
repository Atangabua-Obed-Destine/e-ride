<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A dish or product. is_available false = hidden from customers until the
 * partner turns it back on; sold_out_until = sold out today, back by itself.
 */
class StoreItem extends Model
{
    use HasUuid, SoftDeletes, HasServingTimes;

    // What a customer sees for an item right now (menuState).
    public const AVAILABLE = 'available';
    public const SOLD_OUT = 'sold_out';     // served now, sold out today
    public const LATER = 'later';           // served later today
    public const NOT_TODAY = 'not_today';   // another day, or finished for today
    public const HIDDEN = 'hidden';         // switched off by the partner

    protected $fillable = [
        'store_id', 'store_menu_category_id', 'name', 'description', 'price', 'image', 'is_available', 'sort',
        'served_days', 'served_from', 'served_until', 'sold_out_until',
    ];

    protected $casts = [
        'price' => 'float',
        'is_available' => 'boolean',
        'sort' => 'integer',
        'served_days' => 'integer',
        'sold_out_until' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreMenuCategory::class, 'store_menu_category_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? dynamicStorage('storage/app/public/store/item') . '/' . $this->image : null;
    }

    /** The partner's switch: available, sold_out (today only) or hidden. Ignores serving times. */
    public function switchState(?Carbon $at = null): string
    {
        if (!$this->is_available) {
            return self::HIDDEN;
        }
        return $this->sold_out_until && $this->sold_out_until->gt($at ?? now()) ? self::SOLD_OUT : self::AVAILABLE;
    }

    /** Days served, item and section together, as a mask (127 = every day). */
    public function effectiveDayMask(): int
    {
        $own = $this->served_days ?? self::ALL_DAYS;
        $section = $this->category?->served_days ?? self::ALL_DAYS;
        return $own & $section;
    }

    /** Time window, item and section together: [from, until] or null for all opening hours. */
    public function effectiveWindow(): ?array
    {
        $windows = array_filter([$this->servedWindow(), $this->category?->servedWindow()]);
        if (!$windows) {
            return null;
        }
        return [max(array_column($windows, 0)), min(array_column($windows, 1))];
    }

    /** What a customer sees right now. Needs the category relation (or none). */
    public function menuState(?Carbon $at = null): string
    {
        $at = $at ?? now();
        if (!$this->is_available || ($this->category && !$this->category->is_active)) {
            return self::HIDDEN;
        }
        if (!($this->effectiveDayMask() & self::dayBit($at))) {
            return self::NOT_TODAY;
        }
        $window = $this->effectiveWindow();
        $time = $at->format('H:i');
        if ($window && ($window[0] >= $window[1] || $time >= $window[1])) {
            return self::NOT_TODAY;
        }
        if ($this->switchState($at) === self::SOLD_OUT) {
            return self::SOLD_OUT;
        }
        if ($window && $time < $window[0]) {
            return self::LATER;
        }
        return self::AVAILABLE;
    }

    /** Served on this date at all (day of the week), ignoring the time window and switches. */
    public function servedOn(Carbon $at): bool
    {
        return (bool)($this->effectiveDayMask() & self::dayBit($at));
    }
}
