<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** E-Ride's commission for one month of an agency: app and counter tickets, each at its own %. */
class BusStatement extends Model
{
    use HasUuid;

    public const DUE = 'due';
    public const PAID = 'paid';

    protected $fillable = [
        'agency_id', 'month', 'app_tickets', 'app_fares', 'app_commission', 'counter_tickets', 'counter_fares', 'counter_commission', 'total',
        'due_on', 'status', 'paid_at', 'reference', 'marked_by',
    ];

    protected $casts = [
        'month' => 'date',
        'due_on' => 'date',
        'paid_at' => 'datetime',
        'app_fares' => 'float',
        'app_commission' => 'float',
        'counter_fares' => 'float',
        'counter_commission' => 'float',
        'total' => 'float',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }
}
