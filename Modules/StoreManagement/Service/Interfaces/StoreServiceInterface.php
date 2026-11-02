<?php

namespace Modules\StoreManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Entities\StoreMenuCategory;
use Modules\StoreManagement\Entities\StorePayout;

interface StoreServiceInterface extends BaseServiceInterface
{
    public function list(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function createWithOwner(array $data): Store;

    public function updateWithOwner(Store $store, array $data): Store;

    /** Partner-editable subset: hours, open switch, prep time, minimum order, contact, images. */
    public function updateProfile(Store $store, array $data): Store;

    public function resolveZoneId(float $latitude, float $longitude): ?string;

    public function saveCategory(Store $store, array $data, ?string $id = null): StoreMenuCategory;

    public function deleteCategory(Store $store, string $id): void;

    public function saveItem(Store $store, array $data, ?string $id = null): StoreItem;

    public function deleteItem(Store $store, string $id): void;

    public function setItemAvailability(Store $store, string $id, bool $available): StoreItem;

    public function recordPayout(Store $store, array $data, ?string $adminId): StorePayout;
}
