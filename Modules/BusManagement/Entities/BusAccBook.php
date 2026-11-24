<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A branch's books: where they start, the next entry and document numbers, the default accounts. */
class BusAccBook extends Model
{
    use HasUuid;

    protected $table = 'bus_acc_books';

    protected $fillable = ['agency_id', 'starts_on', 'next_number', 'next_document', 'settings', 'opened_by', 'synced_at'];

    protected $casts = ['starts_on' => 'date', 'settings' => 'array', 'synced_at' => 'datetime'];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }

    public function setting(string $key, $default = null)
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }
}
