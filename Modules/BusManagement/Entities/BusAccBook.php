<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A set of books: a branch's (kind branch, agency_id is the bus agency) or a head office's (kind company,
 * agency_id is the bus company). Where they start, the next entry number, the default accounts.
 */
class BusAccBook extends Model
{
    use HasUuid;

    protected $table = 'bus_acc_books';

    protected $fillable = ['agency_id', 'kind', 'starts_on', 'next_number', 'next_document', 'settings', 'opened_by', 'synced_at'];

    protected $casts = ['starts_on' => 'date', 'settings' => 'array', 'synced_at' => 'datetime'];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }

    public function isCompany(): bool
    {
        return $this->kind === 'company';
    }

    /** The bus agency or bus company these books belong to. */
    public function owner(): BusAgency|BusCompany|null
    {
        return $this->isCompany() ? BusCompany::find($this->agency_id) : BusAgency::find($this->agency_id);
    }

    public function setting(string $key, $default = null)
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }
}
