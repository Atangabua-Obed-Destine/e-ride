<?php

namespace Modules\PromotionManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\UserManagement\Transformers\CustomerLevelResource;
use Modules\UserManagement\Transformers\CustomerResource;

class CouponResource extends JsonResource
{
    public $preserveKeys = false;

    /**
     * Transform the resource into an array.
     *
     * @param  Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "description" => $this->description,
            "zone_coupon" =>$this->zone_coupon ,
            "customer_level_coupon" =>$this->customer_level_coupon ,
            "customer_coupon" =>$this->customer_coupon ,
            "category_coupon" =>$this->category_coupon ,
            "category_coupon_image" =>$this->category_coupon_image ,
            "min_trip_amount" =>$this->min_trip_amount,
            "max_coupon_amount" => $this->max_coupon_amount,
            "coupon" =>$this->coupon ,
            "amount_type" => $this->amount_type,
            "coupon_type" => $this->coupon_type,
            "coupon_code" => $this->coupon_code,
            "limit" =>$this->limit ,
            "start_date" =>$this->start_date ,
            "end_date" => $this->end_date,
            "is_active" =>$this->is_active,
            "is_applied" =>$this->is_applied,
            "created_at" => $this->created_at
        ];
    }
}
