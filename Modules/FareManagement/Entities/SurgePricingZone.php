<?php

namespace Modules\FareManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SurgePricingZone extends Model
{
    use HasFactory;

    protected $table = 'surge_pricing_zones';
    protected $fillable = ['zone_id', 'surge_pricing_id'];

}
