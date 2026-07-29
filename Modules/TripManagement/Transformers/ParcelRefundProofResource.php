<?php

namespace Modules\TripManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParcelRefundProofResource extends JsonResource
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
            'file' => dynamicStorage('storage/app/public/parcel/proof/'.$this->attachment),
        ];
    }
}
