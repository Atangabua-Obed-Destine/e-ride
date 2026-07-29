<?php

namespace Modules\PromotionManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AdminModule\Entities\ActivityLog;
use Modules\TripManagement\Entities\TripRequest;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLevel;
use Modules\ZoneManagement\Entities\Zone;

class CouponSetup extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'zone_coupon_type',
        'customer_level_coupon_type',
        'customer_coupon_type',
        'category_coupon_type',
        'user_id',
        'user_level_id',
        'min_trip_amount',
        'max_coupon_amount',
        'coupon',
        'amount_type',
        'coupon_type',
        'coupon_code',
        'limit',
        'start_date',
        'end_date',
        'rules',
        'total_used',
        'total_amount',
        'is_active',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'category_coupon_type' => 'array',
        'min_trip_amount' => 'string',
        'max_coupon_amount' => 'string',
        'coupon' => 'string',
        'limit' => 'integer',
        'total_used' => 'float',
        'total_amount' => 'float',
        'is_active' => 'integer',
    ];

    public function categories()
    {
        return $this->belongsToMany(VehicleCategory::class)->using('Modules\PromotionManagement\Entities\CouponSetupVehicleCategory')->withTimestamps();
    }

    public function trips()
    {
        return $this->hasMany(TripRequest::class, 'coupon_id');
    }

    public function appliedCoupons()
    {
        return $this->hasMany(AppliedCoupon::class);
    }

    public function vehicleCategories()
    {
        return $this->belongsToMany(VehicleCategory::class, VehicleCategoryCouponSetup::class);
    }

    public function zones()
    {
        return $this->belongsToMany(Zone::class, ZoneCouponSetup::class);
    }

    public function customerLevels()
    {
        return $this->belongsToMany(UserLevel::class, CustomerLevelCouponSetup::class);
    }

    public function customers()
    {
        return $this->belongsToMany(User::class, CustomerCouponSetup::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function level()
    {
        return $this->belongsTo(UserLevel::class, 'user_level_id');
    }

    public function logs()
    {
        return $this->morphMany(ActivityLog::class, 'logable');
    }

    public function getIsAppliedAttribute()
    {
        $user = User::where('id',auth('api')->id())->where('user_type',CUSTOMER)->first();
        return $user && $user->appliedCoupon && $user->appliedCoupon->coupon_setup_id == $this->id;
    }


    public function getZoneCouponAttribute()
    {
        if ($this->zone_coupon_type === ALL) {
            $data[] = ALL;
            return $data;
        }
        $data = [];
        foreach ($this->zones->pluck('name')->toArray() as $zone) {
            $data[] = $zone;
        }
        return $data;
    }

    public function getCustomerLevelCouponAttribute()
    {
        if ($this->customer_level_coupon_type === ALL) {
            $data[] = ALL;
            return $data;
        }
        $data = [];
        foreach ($this->customerLevels->pluck('name')->toArray() as $customerLevel) {
            $data[] = $customerLevel;
        }
        return $data;
    }

    public function getCustomerCouponAttribute()
    {
        if ($this->customer_coupon_type === ALL) {
            $data[] = ALL;
            return $data;
        }
        $data = [];
        foreach ($this->customers as $customer) {
            $data[] = $customer->first_name . ' ' . $customer->last_name;
        }
        return $data;
    }

    public function getCategoryCouponAttribute()
    {
        if (in_array(ALL, $this->category_coupon_type, true)) {
            $data[] = ALL;
            return $data;
        } elseif (in_array(PARCEL, $this->category_coupon_type, true) && in_array(CUSTOM, $this->category_coupon_type, true)) {
            $data[] = "Parcel";
            foreach ($this->vehicleCategories->pluck('name')->toArray() as $vehicleCategory) {
                $data[] = $vehicleCategory;
            }
            return $data;
        } elseif (in_array(PARCEL, $this->category_coupon_type, true)) {
            $data[] = "Parcel";
            return $data;
        } elseif (in_array(CUSTOM, $this->category_coupon_type, true)) {
            $data = [];
            foreach ($this->vehicleCategories->pluck('name')->toArray() as $vehicleCategory) {
                $data[] = $vehicleCategory;
            }
            return $data;
        } else {
            return [];
        }
    }

    public function getCategoryCouponImageAttribute()
    {
        if (in_array(ALL, $this->category_coupon_type, true)) {
            return dynamicAsset('public/assets/admin-module/img/coupon-default.png');
        } elseif (in_array(PARCEL, $this->category_coupon_type, true) && in_array(CUSTOM, $this->category_coupon_type, true)) {
            return dynamicAsset('public/assets/admin-module/img/coupon-default.png');
        } elseif (in_array(PARCEL, $this->category_coupon_type, true)) {
            return dynamicAsset('public/assets/admin-module/img/parcel-coupon.png');
        } elseif (in_array(CUSTOM, $this->category_coupon_type, true)) {
            if (count($this->vehicleCategories) > 1) {
                return dynamicAsset('public/assets/admin-module/img/coupon-default.png');
            } else {
                return onErrorImage(
                    $this->vehicleCategories[0]?->image,
                    dynamicStorage('storage/app/public/vehicle/category') . '/' . $this->vehicleCategories[0]?->image,
                    dynamicAsset('public/assets/admin-module/img/media/car.png'),
                    'vehicle/category/',
                );
            }
        } else {
            return null;
        }
    }

    protected static function newFactory()
    {
        return \Modules\PromotionManagement\Database\factories\CouponSetupFactory::new();
    }

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($item) {
            $array = [];
            foreach ($item->changes as $key => $change) {
                $array[$key] = $item->original[$key];
            }
            if (!empty($array)) {
                $log = new ActivityLog();
                $log->edited_by = auth()->user()->id ?? 'user_update';
                $log->before = $array;
                $log->after = $item->changes;
                $item->logs()->save($log);
            }
        });

        static::deleted(function ($item) {
            $log = new ActivityLog();
            $log->edited_by = auth()->user()->id;
            $log->before = $item->original;
            $item->logs()->save($log);
        });

    }
}
