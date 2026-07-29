<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\UserManagement\Database\factories\ReferralCustomerFactory;

class ReferralCustomer extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'ref_by',
        'ref_by_earning_amount',
        'customer_discount_amount',
        'customer_discount_amount_type',
        'customer_discount_validity',
        'customer_discount_validity_type',
        'is_used',
    ];

    protected static function newFactory(): ReferralCustomerFactory
    {
        //return ReferralCustomerFactory::new();
    }

    public function useRefferalCustomer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    public function shareRefferalCustomer()
    {
        return $this->belongsTo(User::class, 'ref_by');
    }
}
