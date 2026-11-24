<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One E-Ride statement for all of a company's branches in a month (when it chose to be billed together). */
class BusCompanyStatement extends Model
{
    use HasUuid;

    protected $fillable = ['company_id', 'month', 'total', 'due_on', 'status', 'paid_at', 'reference', 'marked_by'];

    protected $casts = ['month' => 'date', 'due_on' => 'date', 'paid_at' => 'datetime', 'total' => 'float'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusCompany::class, 'company_id');
    }

    /** The branch statements it sums. */
    public function lines(): HasMany
    {
        return $this->hasMany(BusStatement::class, 'company_statement_id');
    }
}
