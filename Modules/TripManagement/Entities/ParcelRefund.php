<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\PromotionManagement\Entities\CouponSetup;
use Modules\TripManagement\Database\factories\ParcelRefundFactory;

class ParcelRefund extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'readable_id',
        'trip_request_id',
        'parcel_approximate_price',
        'reason',
        'status',
        'approval_note',
        'deny_note',
        'note',
        'customer_note',
        'refund_amount_by_admin',
        'refund_method',
        'coupon_setup_id',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'parcel_approximate_price' => 'float',
        'refund_amount_by_admin' => 'float',
    ];

    public function refundProofs()
    {
        return $this->hasMany(ParcelRefundProof::class);
    }
    public function tripRequest()
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }
    public function coupon(){
        return $this->belongsTo(CouponSetup::class, 'coupon_setup_id');
    }
    protected static function newFactory(): ParcelRefundFactory
    {
        //return ParcelRefundFactory::new();
    }
}
