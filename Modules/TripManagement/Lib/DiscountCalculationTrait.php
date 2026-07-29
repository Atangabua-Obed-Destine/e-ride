<?php

namespace Modules\TripManagement\Lib;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\PromotionManagement\Entities\DiscountSetup;
use Modules\PromotionManagement\Service\Interfaces\DiscountSetupServiceInterface;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;

trait DiscountCalculationTrait
{

    public function getEstimatedDiscount($user, $zoneId, $tripType, $vehicleCategoryId, $estimatedAmount, $beforeCreate = false)
    {
        $discountSetupService = app(DiscountSetupServiceInterface::class);

        $tripAmountWithoutVatTaxAndTips = $estimatedAmount;
        $criteria = [
            'user_id' => $user->id,
            'level_id' => $user->user_level_id,
            'zone_id' => $zoneId,
            'is_active' => 1,
            'date' => date('Y-m-d'),
            'fare' => $tripAmountWithoutVatTaxAndTips
        ];
        $userTripApplicableDiscounts = $discountSetupService->getUserTripApplicableDiscountList(tripType: $tripType, vehicleCategoryId: $vehicleCategoryId, data: $criteria);
        $adminDiscount = $this->selectBestAdminDiscount(
            $userTripApplicableDiscounts,
            $user,
            $tripAmountWithoutVatTaxAndTips
        );

        $referralDiscountAmount = 0;
        $totalCustomerTrips = $user->customerTrips()->count();
        $totalTrips = $beforeCreate ? ($totalCustomerTrips + 1) : $totalCustomerTrips;
        if (referralEarningSetting('referral_earning_status', CUSTOMER)?->value &&
            $user?->referralCustomerDetails && $user?->referralCustomerDetails?->is_used == 0 && $totalTrips == 1
            && $user?->referralCustomerDetails->customer_discount_amount > 0) {
            if ($user?->referralCustomerDetails?->customer_discount_validity == null || $user?->referralCustomerDetails?->customer_discount_validity == 0 || $user?->referralCustomerDetails->customer_discount_validity_type == null) {
                $referralDiscountAmount = $this->getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips);
            }
            if ($user?->referralCustomerDetails->customer_discount_validity > 0 && $user?->referralCustomerDetails->customer_discount_validity_type != null) {
                $validityTime = Carbon::create($user->created_at);
                if ($user?->referralCustomerDetails->customer_discount_validity_type === 'hour') {
                    $validityTime = $validityTime->addHours((int)$user?->referralCustomerDetails->customer_discount_validity);
                } else {
                    $validityTime = $validityTime->addDays((int)$user?->referralCustomerDetails->customer_discount_validity);
                }
                if ($validityTime >= Carbon::now()) {
                    $referralDiscountAmount = $this->getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips);
                }
            }
        }

        if ($adminDiscount && $referralDiscountAmount) {
            if ($adminDiscount['discount_amount'] > $referralDiscountAmount) {
                return $adminDiscount;
            }
            return collect([
                'discount' => null,
                'discount_id' => null,
                'discount_amount' => $referralDiscountAmount
            ]);
        }
        if ($adminDiscount) {
            return $adminDiscount;
        }
        if ($referralDiscountAmount) {
            return collect([
                'discount' => null,
                'discount_id' => null,
                'discount_amount' => $referralDiscountAmount
            ]);
        }

        return collect([
            'discount' => null,
            'discount_id' => null,
            'discount_amount' => 0
        ]);
    }

    public function getFinalDiscount($user, $trip)
    {
        $discountSetupService = app(DiscountSetupServiceInterface::class);
        $tripAmountWithoutVatTaxAndTips = $trip->paid_fare - $trip->fee->tips - $trip->fee->vat_tax;
        $criteria = [
            'user_id' => $trip->customer_id,
            'level_id' => $trip->customer->user_level_id,
            'zone_id' => $trip->zone_id,
            'is_active' => 1,
            'date' => date('Y-m-d'),
            'fare' => $tripAmountWithoutVatTaxAndTips
        ];
        $userTripApplicableDiscounts = $discountSetupService->getUserTripApplicableDiscountList(tripType: $trip->type, vehicleCategoryId: $trip->vehicle_category_id, data: $criteria);
        $adminDiscount = $this->selectBestAdminDiscount(
            $userTripApplicableDiscounts,
            $user,
            $tripAmountWithoutVatTaxAndTips
        );

        $referralDiscountAmount = 0;
        if (referralEarningSetting('referral_earning_status', CUSTOMER)?->value &&
            $user?->referralCustomerDetails && $user?->referralCustomerDetails?->is_used == 0 && $user->customerTrips()->count() == 1
            && ($user?->referralCustomerDetails->customer_discount_amount > 0)) {
            if ($user?->referralCustomerDetails->customer_discount_validity == 0 && $user?->referralCustomerDetails->customer_discount_validity_type == null) {
                $referralDiscountAmount = $this->getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips);
            }
            if ($user?->referralCustomerDetails->customer_discount_validity > 0 && $user?->referralCustomerDetails->customer_discount_validity_type != null) {
                $validityTime = Carbon::create($user->created_at);
                if ($user?->referralCustomerDetails->customer_discount_validity_type === 'hour') {
                    $validityTime = $validityTime->addHours((int)$user?->referralCustomerDetails->customer_discount_validity);
                } else {
                    $validityTime = $validityTime->addDays((int)$user?->referralCustomerDetails->customer_discount_validity);
                }
                if ($validityTime >= Carbon::now()) {
                    $referralDiscountAmount = $this->getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips);
                }
            }
        }
        if ($adminDiscount && $referralDiscountAmount) {
            if ($adminDiscount['discount_amount'] > $referralDiscountAmount) {
                return $adminDiscount;
            }
            return collect([
                'discount' => null,
                'discount_id' => null,
                'discount_amount' => $referralDiscountAmount
            ]);
        }
        if ($adminDiscount) {
            return $adminDiscount;
        }
        if ($referralDiscountAmount) {
            return collect([
                'discount' => null,
                'discount_id' => null,
                'discount_amount' => $referralDiscountAmount
            ]);
        }
        return collect([
            'discount' => null,
            'discount_id' => null,
            'discount_amount' => 0
        ]);
    }

    private function getDiscountAmount($discount, float $tripAmountWithoutVatTaxAndTips): float
    {
        if ($discount->discount_amount_type === PERCENTAGE) {
            $discountAmount = ($discount->discount_amount * $tripAmountWithoutVatTaxAndTips) / 100;
            $discountAmount = min($discountAmount, $tripAmountWithoutVatTaxAndTips);
            if ($discount->max_discount_amount !== null && $discountAmount > $discount->max_discount_amount) {
                $discountAmount = $discount->max_discount_amount;
            }
            return max(0.0, round($discountAmount, 2));
        }

        return max(0.0, round(min($discount->discount_amount, $tripAmountWithoutVatTaxAndTips), 2));
    }


    private function getReferralCustomerDiscountAmount($user, $tripAmountWithoutVatTaxAndTips)
    {
        if ($user?->referralCustomerDetails?->customer_discount_amount_type == PERCENTAGE) {
            $discountAmount = ($user?->referralCustomerDetails?->customer_discount_amount * $tripAmountWithoutVatTaxAndTips) / 100;
            return round($discountAmount, 2);
        }
        $amount = $tripAmountWithoutVatTaxAndTips;
        if ($user?->referralCustomerDetails?->customer_discount_amount > $amount) {
            return round(min($user?->referralCustomerDetails?->customer_discount_amount, $amount), 2);
        }
        return round((double)$user?->referralCustomerDetails?->customer_discount_amount);
    }

    public function updateDiscountCount($discountId, $amount)
    {
        DiscountSetup::where('id', $discountId)
            ->increment('total_used', 1, ['total_amount' => DB::raw("total_amount + $amount")]);

    }

    private function selectBestAdminDiscount($userTripApplicableDiscounts, $user, float $tripAmountWithoutVatTaxAndTips): ?array {
        if ($userTripApplicableDiscounts->isEmpty()) {
            return null;
        }

        $discountIds = $userTripApplicableDiscounts->pluck('id')->all();

        $usageCounts = TripRequest::query()
            ->where('customer_id', $user->id)
            ->whereIn('discount_id', $discountIds)
            ->where('payment_status', PAID)
            ->select('discount_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('discount_id')
            ->pluck('cnt', 'discount_id');

        $discounts = [];
        foreach ($userTripApplicableDiscounts as $userTripApplicableDiscount) {
            $userAppliedDiscountCount = (int)($usageCounts[$userTripApplicableDiscount->id] ?? 0);
            if ($userTripApplicableDiscount->limit_per_user > $userAppliedDiscountCount) {
                $discounts[] = [
                    'discount' => $userTripApplicableDiscount,
                    'discount_id' => $userTripApplicableDiscount->id,
                    'discount_amount' => $this->getDiscountAmount($userTripApplicableDiscount, $tripAmountWithoutVatTaxAndTips),
                ];
            }
        }

        if (empty($discounts)) {
            return null;
        }

        usort($discounts, fn($a, $b) => $b['discount_amount'] <=> $a['discount_amount']);
        return $discounts[0];
    }
}
