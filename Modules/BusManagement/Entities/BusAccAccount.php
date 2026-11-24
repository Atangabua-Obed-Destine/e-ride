<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/** One account of a chart (OHADA numbering), of a branch's or a head office's books. A debit-normal account grows with debits. */
class BusAccAccount extends Model
{
    use HasUuid;

    protected $table = 'bus_acc_accounts';

    protected $fillable = ['agency_id', 'code', 'name', 'class', 'type', 'money', 'role', 'link_id', 'category', 'active'];

    protected $casts = ['money' => 'boolean', 'category' => 'boolean', 'active' => 'boolean', 'class' => 'integer'];

    public function debitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }

    /** "5711 Counter cash": standard names translated, names typed by people (and branch names) as they are. */
    public function label(): string
    {
        return $this->code . ' ' . $this->displayName();
    }

    public function displayName(): string
    {
        static $standard = null;
        $standard ??= array_flip(array_merge(array_column(\Modules\BusManagement\Lib\AccChart::ACCOUNTS, 1), array_column(\Modules\BusManagement\Lib\AccChart::COMPANY_ACCOUNTS, 1)));
        return isset($standard[$this->name]) ? translate($this->name) : $this->name;
    }
}
