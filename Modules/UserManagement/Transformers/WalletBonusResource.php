<?php

namespace Modules\UserManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletBonusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request $request
     * @return array
     */

    public function toArray($request)
    {
        return [
          'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'bonus_amount' => $this->bonus_amount,
            'amount_type' => $this->amount_type,
            'min_add_amount' => $this->min_add_amount,
            'max_bonus_amount' => $this->max_bonus_amount,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
        ];
    }
}
