<?php

namespace Modules\FareManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SurgePricingTimeSlot extends Model
{
    use HasFactory;

    protected $table = 'surge_pricing_time_slots';
    protected $fillable = ['surge_pricing_id', 'start_date', 'end_date', 'selected_days', 'slots'];
    protected $casts = ['slots' => 'array', 'selected_days' => 'array'];
}
