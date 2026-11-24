<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A bus carried in the books as a fixed asset: its value, when its wear starts, how many months it
 * runs, what it is worth at the end. Straight-line: the same wear every month until the end value.
 */
class BusAccAsset extends Model
{
    use HasUuid;

    protected $table = 'bus_acc_assets';

    protected $fillable = ['agency_id', 'vehicle_id', 'name', 'value', 'end_value', 'life_months', 'starts_on', 'funded_by', 'status', 'document_id',
        'disposed_on', 'sale_price', 'disposal_document_id'];

    protected $casts = ['value' => 'float', 'end_value' => 'float', 'sale_price' => 'float', 'life_months' => 'integer', 'starts_on' => 'date', 'disposed_on' => 'date'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(BusVehicle::class, 'vehicle_id');
    }

    /** The wear of one month (the last month takes what is left). */
    public function monthly(): float
    {
        return round(($this->value - $this->end_value) / max(1, $this->life_months), 2);
    }

    /** The months its wear runs: from the month it starts, for life_months months. */
    public function lastMonth(): Carbon
    {
        return $this->starts_on->copy()->startOfMonth()->addMonthsNoOverflow($this->life_months - 1);
    }
}
