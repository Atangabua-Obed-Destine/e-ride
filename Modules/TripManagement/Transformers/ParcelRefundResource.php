<?php

namespace Modules\TripManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ParcelManagement\Transformers\InformationResource;
use Modules\ParcelManagement\Transformers\UserResource;
use Modules\PromotionManagement\Transformers\CouponResource;
use Modules\PromotionManagement\Transformers\DiscountResource;
use Modules\UserManagement\Transformers\CustomerResource;
use Modules\UserManagement\Transformers\DriverResource;
use Modules\VehicleManagement\Transformers\VehicleModelResource;
use Modules\VehicleManagement\Transformers\VehicleCategoryResource;
use Modules\VehicleManagement\Transformers\VehicleResource;
use Modules\ZoneManagement\Transformers\ZoneResource;

class ParcelRefundResource extends JsonResource
{


    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'tripRequest' => TripRequestResource::make($this->whenLoaded('tripRequest')),
            'attachments' => ParcelRefundProofResource::collection($this->refundProofs),
            'readable_id' => $this->readable_id,
            'parcel_approximate_price' => $this->parcel_approximate_price,
            'reason' => $this->reason,
            'status' => $this->status,
            'approval_note' => $this->approval_note,
            'deny_note' => $this->deny_note,
            'note' => $this->note,
            'customer_note' => $this->customer_note,
            'refund_amount_by_admin' => $this->refund_amount_by_admin,
            'refund_method' => $this->refund_method,
            'coupon_setup_id' => $this->coupon_setup_id != null ? CouponResource::make($this->coupon) : null,
            'coupon_setup_used' => (bool)$this->coupon_setup_id != null && (($this->tripRequest->where('coupon_id', '!=', null)->where('coupon_id', $this->coupon_setup_id)->first() ? true : false)),
        ];
    }
}
