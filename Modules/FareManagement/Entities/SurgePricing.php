<?php

namespace Modules\FareManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Gateways\Traits\HasUuid;
use Modules\ZoneManagement\Entities\Zone;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SurgePricing extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'surge_pricing';
    protected $fillable = ['readable_id', 'name', 'zone', 'surge_pricing_for','all_vehicle_surge_percent', 'increase_for_all_vehicles','increase_for_all_parcels','all_parcel_surge_percent', 'zone_setup_type', 'schedule', 'is_active', 'customer_note'];

    public function surgePricingZones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'surge_pricing_zones', 'surge_pricing_id', 'zone_id');
    }

    public function surgePricingServiceCategories(): HasMany
    {
        return $this->hasMany(SurgePricingServiceCategory::class);
    }

    public function surgePricingTimeSlot(): HasOne
    {
        return $this->hasOne(SurgePricingTimeSlot::class, 'surge_pricing_id');
    }
}
