<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gateways\Traits\HasUuid;
use Modules\TripManagement\Database\factories\ParcelRefundProofFactory;

class ParcelRefundProof extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'parcel_refund_id',
        'attachment'
    ];

    public function parcelRefund()
    {
        return $this->belongsTo(ParcelRefund::class);
    }

    protected static function newFactory(): ParcelRefundProofFactory
    {
        //return ParcelRefundProofFactory::new();
    }
}
