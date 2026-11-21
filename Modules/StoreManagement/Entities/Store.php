<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Entities\Zone;

/**
 * A partner restaurant or shop: one kitchen at one address. Its staff log into
 * the /partner portal with the linked user (user_type partner).
 *
 * Branches: a store with parent_id is a branch of that main store (the brand).
 * The brand owns the menu, photos and rating; each kitchen (the main store and
 * its branches) has its own location, hours, open switch, orders and login, and
 * its own switches per dish (StoreItemState). The main store's login is the owner.
 */
class Store extends Model
{
    use HasUuid, SoftDeletes;

    public const TYPES = ['restaurant', 'shop'];
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected $fillable = [
        'user_id', 'name', 'slug', 'type', 'phone', 'whatsapp', 'address', 'latitude', 'longitude', 'zone_id',
        'logo', 'cover', 'description', 'opening_hours', 'is_open', 'offers_delivery', 'default_prep_minutes', 'min_order_amount',
        'is_active', 'sort', 'payout_method', 'payout_number', 'payout_name', 'parent_id', 'branch_name',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'opening_hours' => 'array',
        'is_open' => 'boolean',
        'offers_delivery' => 'boolean',
        'is_active' => 'boolean',
        'default_prep_minutes' => 'integer',
        'min_order_amount' => 'float',
        'sort' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'parent_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Store::class, 'parent_id')->orderBy('branch_name');
    }

    /** This kitchen's switches per dish (sold out today, not made here). */
    public function itemStates(): HasMany
    {
        return $this->hasMany(StoreItemState::class);
    }

    /** The brand: the main store, which owns the menu. Itself for a main store. */
    public function brand(): Store
    {
        return $this->parent_id ? ($this->parent ?? $this) : $this;
    }

    public function isBranch(): bool
    {
        return (bool)$this->parent_id;
    }

    /** Every kitchen of the brand: the main store first, then its branches. */
    public function kitchens(bool $activeOnly = false): Collection
    {
        $brand = $this->brand();
        $all = collect([$brand])->merge($brand->relationLoaded('branches') ? $brand->branches : $brand->branches()->get());
        return ($activeOnly ? $all->filter(fn(Store $k) => $k->is_active) : $all)->values();
    }

    /** The ways an order can be had here: delivery unless the store turned it off; eating there at restaurants; collecting always. */
    public function ways(): array
    {
        return array_values(array_filter([
            ($this->offers_delivery ?? true) ? 'delivery' : null,
            $this->type === 'restaurant' ? 'dine_in' : null,
            'pickup',
        ]));
    }

    public function delivers(): bool
    {
        return in_array('delivery', $this->ways(), true);
    }

    /** "Romz · Mile 6" for a kitchen with a branch name, else the brand name. */
    public function displayName(): string
    {
        $name = $this->brand()->name;
        return $this->branch_name ? $name . ' · ' . $this->branch_name : $name;
    }

    /** This kitchen's switches, keyed by dish id. */
    public function stateMap(): Collection
    {
        return StoreItemState::where('store_id', $this->id)->get()->keyBy('store_item_id');
    }

    /** Rating across every kitchen of the brand: customers judge the brand. */
    public function brandRatingSummary(): array
    {
        $ids = $this->kitchens()->pluck('id');
        $q = StoreReview::whereIn('store_id', $ids)->where('is_hidden', false);
        $count = (clone $q)->count();
        $avg = $count ? (clone $q)->avg('rating') : null;
        return ['rating' => $avg !== null ? round((float)$avg, 1) : null, 'rating_count' => $count];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(StoreMenuCategory::class)->orderBy('sort')->orderBy('name');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreItem::class)->orderBy('sort')->orderBy('name');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(StorePayout::class);
    }

    /** Ratings customers can see: hidden ones (abuse, mistakes) are left out. */
    public function reviews(): HasMany
    {
        return $this->hasMany(StoreReview::class)->where('is_hidden', false);
    }

    /** Average rating (one decimal) and count, from withAvg/withCount when loaded. */
    public function ratingSummary(): array
    {
        $count = (int)($this->reviews_count ?? $this->reviews()->count());
        $avg = $this->reviews_avg_rating ?? ($count ? $this->reviews()->avg('rating') : null);
        return ['rating' => $avg !== null ? round((float)$avg, 1) : null, 'rating_count' => $count];
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? dynamicStorage('storage/app/public/store/logo') . '/' . $this->logo : ($this->parent_id ? $this->brand()->logoUrl() : null);
    }

    public function coverUrl(): ?string
    {
        return $this->cover ? dynamicStorage('storage/app/public/store/cover') . '/' . $this->cover : ($this->parent_id ? $this->brand()->coverUrl() : null);
    }

    /** Today's hours as [open, close] ("08:00", "22:00"), or null when closed all day. */
    public function hoursFor(Carbon $at): ?array
    {
        $hours = $this->opening_hours ?? [];
        if (empty($hours)) {
            return ['00:00', '23:59'];
        }
        $day = $hours[self::DAYS[$at->dayOfWeekIso - 1]] ?? null;
        if (!$day || !empty($day['closed']) || empty($day['open']) || empty($day['close'])) {
            return null;
        }
        return [$day['open'], $day['close']];
    }

    /**
     * Taking orders right now: active, switched open by the partner, and
     * inside today's hours. Hours past midnight ("18:00" to "02:00") are
     * not supported: close is clamped to the end of the day.
     */
    public function isOpenNow(?Carbon $at = null): bool
    {
        if (!$this->is_active || !$this->is_open) {
            return false;
        }
        $at = $at ?? now();
        $hours = $this->hoursFor($at);
        if (!$hours) {
            return false;
        }
        $time = $at->format('H:i');
        return $time >= $hours[0] && $time <= $hours[1];
    }

    /** Points each loaded item at its loaded section, so menu checks need no extra queries. */
    public function linkMenu(): self
    {
        $categories = $this->categories->keyBy('id');
        foreach ($this->items as $item) {
            $item->setRelation('category', $item->store_menu_category_id ? $categories->get($item->store_menu_category_id) : null);
        }
        return $this;
    }

    /** The brand's unpaid balance: delivered food earnings at every kitchen minus payouts (paid to the owner). */
    public function balance(): float
    {
        $brand = $this->brand();
        $earned = (float)StoreOrder::whereIn('store_id', $brand->kitchens()->pluck('id'))->where('status', StoreOrder::DELIVERED)->sum('partner_earning');
        $paid = (float)$brand->payouts()->sum('amount');
        return round($earned - $paid, 2);
    }
}
