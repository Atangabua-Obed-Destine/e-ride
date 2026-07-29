<?php

namespace Modules\PromotionManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\PromotionManagement\Repository\CouponSetupRepositoryInterface;
use Modules\PromotionManagement\Service\Interfaces\CouponSetupServiceInterface;
use Modules\TripManagement\Repository\TripRequestRepositoryInterface;

class CouponSetupService extends BaseService implements CouponSetupServiceInterface
{
    protected $tripRequestRepository;
    protected $couponSetupRepository;

    public function __construct(CouponSetupRepositoryInterface $couponSetupRepository, TripRequestRepositoryInterface $tripRequestRepository)
    {
        parent::__construct($couponSetupRepository);
        $this->couponSetupRepository = $couponSetupRepository;
        $this->tripRequestRepository = $tripRequestRepository;

    }

    public function create(array $data): ?Model
    {
        DB::beginTransaction();
        $storeData = [
            'name' => $data['coupon_title'],
            'description' => $data['short_desc'],
            'min_trip_amount' => $data['coupon_type'] == 'first_trip' ? 0 : $data['minimum_trip_amount'],
            'max_coupon_amount' => $data['max_coupon_amount'] == null ? 0 : $data['max_coupon_amount'],
            'coupon' => $data['coupon'],
            'coupon_code' => $data['coupon_code'],
            'coupon_type' => $data['coupon_type'],
            'amount_type' => $data['amount_type'],
            'limit' => $data['limit_same_user'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
        ];
        if (in_array(ALL, $data['zone_coupon_type'], true)) {
            $storeData = array_merge($storeData, ['zone_coupon_type' => ALL]);
        }
        if (in_array(ALL, $data['customer_level_coupon_type'], true)) {
            $storeData = array_merge($storeData, ['customer_level_coupon_type' => ALL]);
        }
        if (in_array(ALL, $data['customer_coupon_type'], true)) {
            $storeData = array_merge($storeData, ['customer_coupon_type' => ALL]);
        }
        if (in_array(ALL, $data['category_coupon_type'], true)) {
            $storeData = array_merge($storeData, ['category_coupon_type' => [ALL]]);
            $categoryCoupon = null;
        }
        if (in_array(PARCEL, $data['category_coupon_type'], true) && count($data['category_coupon_type']) === 1) {
            $storeData = array_merge($storeData, ['category_coupon_type' => [PARCEL]]);
            $categoryCoupon = null;
        }
        if (in_array(PARCEL, $data['category_coupon_type'], true) && count($data['category_coupon_type']) > 1) {
            $storeData = array_merge($storeData, ['category_coupon_type' => [PARCEL, CUSTOM]]);
            $categoryCoupon = CUSTOM;
        }
        if (!in_array(ALL, $data['category_coupon_type'], true) && !in_array(PARCEL, $data['category_coupon_type'], true) && count($data['category_coupon_type']) > 0) {
            $storeData = array_merge($storeData, ['category_coupon_type' => [CUSTOM]]);
            $categoryCoupon = CUSTOM;
        }
        $coupon = $this->couponSetupRepository->create(data: $storeData);

        if (!in_array(ALL, $data['zone_coupon_type'], true)) {
            $coupon?->zones()->attach($data['zone_coupon_type']);
        }
        if (!in_array(ALL, $data['customer_level_coupon_type'], true)) {
            $coupon?->customerLevels()->attach($data['customer_level_coupon_type']);
        }
        if (!in_array(ALL, $data['customer_coupon_type'], true)) {
            $coupon?->customers()->attach($data['customer_coupon_type']);
        }
        if ($categoryCoupon && $categoryCoupon == CUSTOM) {
            $data = array_diff($data['category_coupon_type'], array(PARCEL));
            $data = array_diff($data, array(ALL));
            $coupon?->vehicleCategories()->attach($data);
        }
        DB::commit();
        return $coupon;
    }

    public function update(int|string $id, array $data = []): ?Model
    {
        $model = $this->findOne(id: $id);
        DB::beginTransaction();
        $updateData = [
            'name' => $data['coupon_title'],
            'description' => $data['short_desc'],
            'min_trip_amount' => $data['coupon_type'] == 'first_trip' ? 0 : $data['minimum_trip_amount'],
            'max_coupon_amount' => $data['max_coupon_amount'] == null ? 0 : $data['max_coupon_amount'],
            'coupon' => $data['coupon'],
            'coupon_type' => $data['coupon_type'],
            'amount_type' => $data['amount_type'],
            'limit' => $data['limit_same_user'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
        ];
        if (in_array(ALL, $data['zone_coupon_type'], true)) {
            $updateData = array_merge($updateData, ['zone_coupon_type' => ALL]);
            $model?->zones()->detach();
        }else {
            $updateData = array_merge($updateData, ['zone_coupon_type' => CUSTOM]);
        }
        if (in_array(ALL, $data['customer_level_coupon_type'], true)) {
            $updateData = array_merge($updateData, ['customer_level_coupon_type' => ALL]);
            $model?->customerLevels()->detach();
        }else {
            $updateData = array_merge($updateData, ['customer_level_coupon_type' => CUSTOM]);
        }
        if (in_array(ALL, $data['customer_coupon_type'], true)) {
            $updateData = array_merge($updateData, ['customer_coupon_type' => ALL]);
            $model?->customers()->detach();
        }else {
            $updateData = array_merge($updateData, ['customer_coupon_type' => CUSTOM]);
        }
        if (in_array(ALL, $data['category_coupon_type'], true)) {
            $updateData = array_merge($updateData, ['category_coupon_type' => [ALL]]);
            $model?->vehicleCategories()->detach();
            $categoryCoupon = null;
        }
        if (in_array(PARCEL, $data['category_coupon_type'], true) && count($data['category_coupon_type']) === 1) {
            $updateData = array_merge($updateData, ['category_coupon_type' => [PARCEL]]);
            $categoryCoupon = null;
        }
        if (in_array(PARCEL, $data['category_coupon_type'], true) && count($data['category_coupon_type']) > 1) {
            $updateData = array_merge($updateData, ['category_coupon_type' => [PARCEL, CUSTOM]]);
            $categoryCoupon = CUSTOM;
        }
        if (!in_array(ALL, $data['category_coupon_type'], true) && !in_array(PARCEL, $data['category_coupon_type'], true) && count($data['category_coupon_type']) > 0) {
            $updateData = array_merge($updateData, ['category_coupon_type' => [CUSTOM]]);
            $categoryCoupon = CUSTOM;
        }
        $coupon = $this->couponSetupRepository->update(id: $id, data: $updateData);


        if (!in_array(ALL, $data['zone_coupon_type'], true)) {
            $coupon?->zones()->sync($data['zone_coupon_type']);
        }
        if (!in_array(ALL, $data['customer_level_coupon_type'], true)) {
            $coupon?->customerLevels()->sync($data['customer_level_coupon_type']);
        }
        if (!in_array(ALL, $data['customer_coupon_type'], true)) {
            $coupon?->customers()->sync($data['customer_coupon_type']);
        }
        if ($categoryCoupon && $categoryCoupon == CUSTOM) {
            $data = array_diff($data['category_coupon_type'], array(PARCEL));
            $data = array_diff($data, array(ALL));
            $coupon?->vehicleCategories()->sync($data);
        }
        DB::commit();
        return $coupon;
    }

    public function getCardValues($dateRange)
    {
        $coupon = $this->couponSetupRepository->fetchCouponDataCount(dateRange: $dateRange, status: 'active');
        $trip = $this->tripRequestRepository->fetchTripData(dateRange: $dateRange);
        return [
            'total_coupon_amount' => $trip->sum('coupon_amount'),
            'total_active' => $coupon,
        ];
    }

    public function getUserCouponList(array $data, $limit = null, $offset = null)
    {
        return $this->couponSetupRepository->getUserCouponList(data: $data, limit: $limit, offset: $offset);
    }

    public function getAppliedCoupon($tripType, $vehicleCategoryId, array $data){
        return $this->couponSetupRepository->getAppliedCoupon(tripType:$tripType, vehicleCategoryId:  $vehicleCategoryId, data:  $data);
    }


}
