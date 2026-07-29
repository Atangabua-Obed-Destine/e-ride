<?php

namespace Modules\FareManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SurgePricingServiceCategory extends Model
{
    use HasFactory;

    protected $table = 'surge_pricing_service_categories';
    protected $fillable = ['surge_pricing_id', 'service_category_id', 'service_category_type', 'surge_multiplier'];

    public function serviceCategory()
    {
        return $this->morphTo();
    }
}
