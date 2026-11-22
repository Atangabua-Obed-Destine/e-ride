<?php

namespace Modules\StoreManagement\Service;

use App\Service\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Entities\StoreItemState;
use Modules\StoreManagement\Entities\StoreMenuCategory;
use Modules\StoreManagement\Entities\StorePayout;
use Modules\StoreManagement\Repository\StoreRepositoryInterface;
use Modules\StoreManagement\Service\Interfaces\StoreServiceInterface;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

class StoreService extends BaseService implements StoreServiceInterface
{
    /** Menu switch for a brand with branches: back on the menu at every branch. */
    public const ON_MENU = 'on_menu';

    public function __construct(
        protected StoreRepositoryInterface $storeRepository,
        protected ZoneServiceInterface $zoneService,
    )
    {
        parent::__construct($storeRepository);
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Store::query()
            ->with(['owner', 'zone'])
            ->withCount(['items', 'reviews', 'orders as delivered_orders_count' => fn($q) => $q->where('status', 'delivered')])
            ->withAvg('reviews', 'rating')
            ->when(($filters['search'] ?? '') !== '', function ($q) use ($filters) {
                $q->where(fn($w) => $w->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('phone', 'like', '%' . $filters['search'] . '%'));
            })
            ->when(in_array($filters['status'] ?? 'all', ['active', 'inactive'], true), fn($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->with('parent')->withCount('branches')
            ->orderByRaw('COALESCE((SELECT p.name FROM stores p WHERE p.id = stores.parent_id), stores.name)')
            ->orderByRaw('stores.parent_id IS NOT NULL')
            ->orderBy('branch_name')
            ->paginate($perPage)
            ->appends($filters);
    }

    public function createWithOwner(array $data): Store
    {
        return DB::transaction(function () use ($data) {
            $owner = User::create([
                'first_name' => $data['owner_name'],
                'email' => strtolower(trim($data['owner_email'])),
                'password' => Hash::make($data['password']),
                'user_type' => PARTNER_USER_TYPE,
                'is_active' => 1,
            ]);

            $store = new Store($this->storeAttributes($data));
            $store->user_id = $owner->id;
            $store->slug = $this->uniqueSlug($data['name']);
            $store->logo = fileUploader('store/logo/', APPLICATION_IMAGE_FORMAT, $data['logo'] ?? null);
            $store->cover = fileUploader('store/cover/', APPLICATION_IMAGE_FORMAT, $data['cover'] ?? null);
            $store->save();

            return $store;
        });
    }

    public function updateWithOwner(Store $store, array $data): Store
    {
        return DB::transaction(function () use ($store, $data) {
            $owner = $store->owner;
            if ($owner) {
                $owner->first_name = $data['owner_name'];
                $owner->email = strtolower(trim($data['owner_email']));
                if (filled($data['password'] ?? null)) {
                    $owner->password = Hash::make($data['password']);
                }
                $owner->is_active = (bool)($data['is_active'] ?? $store->is_active);
                $owner->save();
            }

            if ($store->name !== $data['name']) {
                $store->slug = $this->uniqueSlug($data['name'], $store->id);
            }
            $store->fill($this->storeAttributes($data));
            $this->applyImages($store, $data);
            $store->save();

            return $store;
        });
    }

    public function updateProfile(Store $store, array $data): Store
    {
        $store->fill(array_intersect_key($data, array_flip([
            'phone', 'whatsapp', 'description', 'is_open', 'offers_delivery', 'default_prep_minutes', 'min_order_amount',
            'payout_method', 'payout_number', 'payout_name', 'own_delivery', 'own_delivery_km',
        ])));
        if (array_key_exists('own_delivery_fee', $data)) {
            $store->own_delivery_fee = (float)($data['own_delivery_fee'] ?? 0);
        }
        if (array_key_exists('reseller_pct', $data)) {
            // Off when empty or 0; otherwise within the admin's limits.
            $pct = (float)($data['reseller_pct'] ?? 0);
            $settings = \Modules\StoreManagement\Lib\StoreSettings::all();
            $store->reseller_pct = $pct > 0 ? round(min($settings['reseller_max_percent'], max($settings['reseller_min_percent'], $pct)), 2) : null;
        }
        if (array_key_exists('opening_hours', $data)) {
            $store->opening_hours = $this->normaliseHours($data['opening_hours']);
        }
        $this->applyImages($store, $data);
        $store->save();
        return $store;
    }

    public function resolveZoneId(float $latitude, float $longitude): ?string
    {
        return $this->zoneService->getByPoints(new Point($latitude, $longitude))->where('is_active', 1)->first()?->id;
    }

    public function saveCategory(Store $store, array $data, ?string $id = null): StoreMenuCategory
    {
        $category = $id ? $store->categories()->findOrFail($id) : new StoreMenuCategory(['store_id' => $store->id, 'is_active' => true]);
        // Only what the form sent: renaming a section keeps its switch and serving times.
        $category->name = trim($data['name']);
        if (array_key_exists('sort', $data)) {
            $category->sort = (int)$data['sort'];
        }
        if (array_key_exists('is_active', $data)) {
            $category->is_active = (bool)$data['is_active'];
        }
        if (!empty($data['schedule'])) {
            $category->fill($this->servingTimes($data));
        }
        $category->store_id = $store->id;
        $category->save();
        return $category;
    }

    public function deleteCategory(Store $store, string $id): void
    {
        $category = $store->categories()->findOrFail($id);
        DB::transaction(function () use ($store, $category) {
            // Items keep existing, just without a section.
            $store->items()->where('store_menu_category_id', $category->id)->update(['store_menu_category_id' => null]);
            $category->delete();
        });
    }

    public function saveItem(Store $store, array $data, ?string $id = null): StoreItem
    {
        $item = $id ? $store->items()->findOrFail($id) : new StoreItem();
        $categoryId = $data['store_menu_category_id'] ?? null;
        if ($categoryId && !$store->categories()->whereKey($categoryId)->exists()) {
            $categoryId = null;
        }
        $item->fill([
            'store_menu_category_id' => $categoryId,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'price' => round((float)$data['price'], 2),
            'sort' => (int)($data['sort'] ?? $item->sort ?? 0),
        ]);
        // A new item is on the menu; editing keeps the switch unless the form sends it.
        if (array_key_exists('is_available', $data) || !$item->exists) {
            $item->is_available = (bool)($data['is_available'] ?? true);
        }
        if (!empty($data['schedule'])) {
            $item->fill($this->servingTimes($data));
        }
        $item->store_id = $store->id;
        if (!empty($data['image'])) {
            $item->image = fileUploader('store/item/', APPLICATION_IMAGE_FORMAT, $data['image'], $item->image);
        }
        $item->save();
        return $item;
    }

    public function deleteItem(Store $store, string $id): void
    {
        $store->items()->findOrFail($id)->delete();
    }

    public function setItemState(Store $kitchen, string $id, string $state): StoreItem
    {
        $brand = $kitchen->brand();
        $item = $brand->items()->findOrFail($id);
        // A store without branches has one switch: Hidden is the menu's, the rest its kitchen's.
        $single = !$kitchen->isBranch() && !$brand->branches()->exists();
        DB::transaction(function () use ($kitchen, $item, $state, $single) {
            if (in_array($state, [StoreItem::HIDDEN, self::ON_MENU], true) || $single) {
                $item->is_available = $state !== StoreItem::HIDDEN;
                $item->save();
            }
            if (in_array($state, [StoreItem::HIDDEN, self::ON_MENU], true)) {
                return;
            }
            $row = StoreItemState::firstOrNew(['store_id' => $kitchen->id, 'store_item_id' => $item->id]);
            if ($state === StoreItem::AVAILABLE) {
                $row->exists && $row->delete();
                return;
            }
            // Sold out today: back on by itself at midnight.
            $row->sold_out_until = $state === StoreItem::SOLD_OUT ? now()->addDay()->startOfDay() : null;
            $row->not_here = $state === StoreItem::NOT_HERE;
            $row->save();
        });
        return $item;
    }

    /** served_days[] and served_from/served_until from a menu form, ready to fill. */
    protected function servingTimes(array $data): array
    {
        $allDay = empty($data['served_from']) || empty($data['served_until']);
        return [
            'served_days' => StoreItem::daysToMask($data['served_days'] ?? null),
            'served_from' => $allDay ? null : $data['served_from'],
            'served_until' => $allDay ? null : $data['served_until'],
        ];
    }

    public function recordPayout(Store $store, array $data, ?string $adminId): StorePayout
    {
        return StorePayout::create([
            'store_id' => $store->id,
            'amount' => round((float)$data['amount'], 2),
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $adminId,
        ]);
    }

    protected function storeAttributes(array $data): array
    {
        $latitude = (float)$data['latitude'];
        $longitude = (float)$data['longitude'];
        // A branch carries its brand's name and type.
        $parent = !empty($data['parent_id']) ? Store::whereNull('parent_id')->find($data['parent_id']) : null;
        return [
            'parent_id' => $parent?->id,
            'branch_name' => filled($data['branch_name'] ?? null) ? trim($data['branch_name']) : null,
            'name' => $parent ? $parent->name : trim($data['name']),
            'type' => $parent ? $parent->type : (in_array($data['type'] ?? '', Store::TYPES, true) ? $data['type'] : 'restaurant'),
            'phone' => trim($data['phone']),
            'whatsapp' => $data['whatsapp'] ?? null,
            'address' => trim($data['address']),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'zone_id' => $this->resolveZoneId($latitude, $longitude),
            'description' => $data['description'] ?? null,
            'opening_hours' => $this->normaliseHours($data['opening_hours'] ?? null),
            'default_prep_minutes' => (int)($data['default_prep_minutes'] ?? 20),
            'min_order_amount' => (float)($data['min_order_amount'] ?? 0),
            'is_active' => (bool)($data['is_active'] ?? false),
            'is_open' => (bool)($data['is_open'] ?? true),
            'offers_delivery' => (bool)($data['offers_delivery'] ?? true),
            'sort' => (int)($data['sort'] ?? 0),
            'payout_method' => $data['payout_method'] ?? null,
            'payout_number' => $data['payout_number'] ?? null,
            'payout_name' => $data['payout_name'] ?? null,
        ];
    }

    protected function applyImages(Store $store, array $data): void
    {
        if (!empty($data['logo'])) {
            $store->logo = fileUploader('store/logo/', APPLICATION_IMAGE_FORMAT, $data['logo'], $store->logo);
        }
        if (!empty($data['cover'])) {
            $store->cover = fileUploader('store/cover/', APPLICATION_IMAGE_FORMAT, $data['cover'], $store->cover);
        }
    }

    /**
     * Form input -> {"mon": {"open": "08:00", "close": "22:00", "closed": false}, ...}.
     * Returns null (open all day, every day) when no day has hours.
     */
    protected function normaliseHours(?array $hours): ?array
    {
        if (!$hours) {
            return null;
        }
        $anySet = collect(Store::DAYS)->contains(fn($day) => !empty($hours[$day]['closed'])
            || (!empty($hours[$day]['open']) && !empty($hours[$day]['close'])));
        if (!$anySet) {
            return null;
        }
        $out = [];
        foreach (Store::DAYS as $day) {
            $row = $hours[$day] ?? [];
            $open = preg_match('/^\d{2}:\d{2}$/', $row['open'] ?? '') ? $row['open'] : null;
            $close = preg_match('/^\d{2}:\d{2}$/', $row['close'] ?? '') ? $row['close'] : null;
            $out[$day] = [
                'open' => $open,
                'close' => $close,
                'closed' => !empty($row['closed']) || !$open || !$close,
            ];
        }
        return $out;
    }

    protected function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;
        $n = 2;
        while (Store::withTrashed()->where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }
}
