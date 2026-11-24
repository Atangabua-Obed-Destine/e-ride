<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One debit or credit of an entry, with its tags (trip, bus, route, channel, counter agent). */
class BusAccLine extends Model
{
    protected $table = 'bus_acc_lines';

    public $timestamps = false;

    protected $fillable = ['entry_id', 'agency_id', 'account_id', 'date', 'debit', 'credit', 'memo', 'departure_id', 'vehicle_id', 'destination_id', 'channel', 'staff_user_id'];

    protected $casts = ['date' => 'date', 'debit' => 'float', 'credit' => 'float'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(BusAccAccount::class, 'account_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BusAccEntry::class, 'entry_id');
    }
}
