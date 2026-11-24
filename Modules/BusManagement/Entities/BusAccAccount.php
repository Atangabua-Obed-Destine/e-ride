<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/** One account of a branch's chart (OHADA numbering). A debit-normal account grows with debits. */
class BusAccAccount extends Model
{
    use HasUuid;

    protected $table = 'bus_acc_accounts';

    protected $fillable = ['agency_id', 'code', 'name', 'class', 'type', 'money', 'role', 'category', 'active'];

    protected $casts = ['money' => 'boolean', 'category' => 'boolean', 'active' => 'boolean', 'class' => 'integer'];

    public function debitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }

    /** "5711 Counter cash", the name translated. */
    public function label(): string
    {
        return $this->code . ' ' . translate($this->name);
    }
}
