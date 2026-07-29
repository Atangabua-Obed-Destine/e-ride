<?php

namespace Modules\UserManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\VehicleManagement\Transformers\VehicleResource;

class DriverResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $trips = $this->driverTrips->where('payment_status', PAID);
        $tips = $trips->sum('tips');
        $totalEarning = $trips->sum('paid_fare');
        $totalCommission = 0;
        foreach ($trips as $trip) {
            $totalCommission += $trip?->fee?->admin_commission ?? 0;
        }
        $paidAmount = $this->transactions?->where('attribute', 'admin_cash_collect')
            ->where('account' , 'payable_balance')->sum('debit') ?? 0;
        $levelUpRewardAmount = $this->transactions?->where('attribute', 'level_reward')->sum('credit') ?? 0;
        return [
            'id' => $this->id,
            'first_name' => $this?->first_name,
            'last_name' => $this?->last_name,
            'level' => DriverLevelResource::make($this->whenLoaded('level')),
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'identification_number' => $this->identification_number,
            'identification_type' => $this->identification_type,
            'identification_image' => $this->identification_image,
            'old_identification_image' => $this->old_identification_image,
            'other_documents' => $this->other_documents,
            'date_of_birth' => $this->date_of_birth,
            'profile_image' => $this->profile_image,
            'fcm_token' => $this->fcm_token,
            'phone_verified_at' => $this->phone_verified_at,
            'email_verified_at' => $this->email_verified_at,
            'user_type' => $this->user_type,
            'remember_token' => $this->remember_token,
            'is_active' => (int) $this->is_active,
            'is_online' => $this->is_online,
            'details' => $this->whenLoaded('driverDetails'),
            'time_track' => TimeTrackResource::make($this->whenLoaded('latestTrack')),
            'last_location' => $this->whenLoaded('lastLocations'),
            'loyalty_points' => $this->loyalty_points,
            'wallet' => UserAccountResource::make($this->whenLoaded('userAccount')),
            'rating' => round($this->received_reviews_avg_rating, 1),
            'total_review' => (int)($this->received_reviews_count),
            'trip_income' => ($totalEarning - $totalCommission - $tips),
            'total_commission' => $totalCommission,
            'total_earning' => $totalEarning,
            'total_tips' => $tips,
            'paid_amount' => $paidAmount,
            'level_up_reward_amount' => $levelUpRewardAmount,
            'is_verified' => (int) ($this->driverDetails?->is_verified ?? 0),
            'is_suspended' => (int) ($this->driverDetails?->is_suspended ?? 0),
            'suspend_reason' => $this->driverDetails?->suspend_reason ?? null,
            'trigger_verification_at' => $this->driverDetails?->trigger_verification_at ?? null,
            'need_verification' => (bool) $this->driverIdentityVerification,
            'additional_data' => $this->whenLoaded('additionalInfo', fn () => $this->additionalInfo
                ? new AdditionalDataResource($this->additionalInfo, DRIVER)
                : []),
            'logged_in_via' => $this->logged_in_via,
        ];
    }
}
