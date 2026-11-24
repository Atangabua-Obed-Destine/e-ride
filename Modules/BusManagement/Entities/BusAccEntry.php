<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A posted journal entry. Never edited or deleted: a mistake is undone by a reversing entry. */
class BusAccEntry extends Model
{
    use HasUuid;

    protected $table = 'bus_acc_entries';

    protected $fillable = ['agency_id', 'number', 'date', 'journal', 'memo', 'source_type', 'source_id', 'source_key', 'total', 'reverses_id', 'reversed_by_id', 'created_by'];

    protected $casts = ['date' => 'date', 'total' => 'float'];

    public function lines(): HasMany
    {
        return $this->hasMany(BusAccLine::class, 'entry_id')->orderBy('id');
    }
}
