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
use Modules\StoreManagement\Entities\StoreMenuCategory;
use Modules\StoreManagement\Entities\StorePayout;
use Modules\StoreManagement\Repository\StoreRepositoryInterface;
use Modules\StoreManagement\Service\Interfaces\StoreServiceInterface;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

class StoreService extends BaseService implements StoreServiceInterface
{
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
            ->orderBy('sort')->orderBy('name')
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
            'phone', 'whatsapp', 'description', 'is_open', 'default_prep_minutes', 'min_order_amount',
            'payout_method', 'payout_number', 'payout_name',
        ])));
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
        $category = $id ? $store->categories()->findOrFail($id) : new StoreMenuCategory(['store_id' => $store->id]);
        $category->fill([
            'name' => trim($data['name']),
            'sort' => (int)($data['sort'] ?? $category->sort ?? 0),
            'is_active' => (bool)($data['is_active'] ?? true),
        ]);
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
            'is_available' => (bool)($data['is_available'] ?? true),
            'sort' => (int)($data['sort'] ?? $item->sort ?? 0),
        ]);
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

    public function setItemAvailability(Store $store, string $id, bool $available): StoreItem
    {
        $item = $store->items()->findOrFail($id);
        $item->is_available = $available;
        $item->save();
        return $item;
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
        return [
            'name' => trim($data['name']),
            'type' => in_array($data['type'] ?? '', Store::TYPES, true) ? $data['type'] : 'restaurant',
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
