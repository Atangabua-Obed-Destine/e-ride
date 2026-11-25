<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An income category (paxhitestbed IncomeCategory). Its ledger account is the
 * income_category mapping keyed by its id (Mapping settings), '' = any other.
 * The name is paxhitestbed's "title" (read as $category->title too).
 */
class IncomeCategory extends Model
{
    use SoftDeletes, Auditable, BelongsToBusiness;

    protected $table = 'income_categories';

    protected $fillable = ['business_id', 'name', 'slug', 'parent_id', 'description', 'status', 'created_by'];

    protected $casts = ['status' => 'boolean'];

    public function getTitleAttribute()
    {
        return $this->name;
    }

    public function incomes()
    {
        return $this->hasMany(Income::class, 'category_id');
    }

    public function getAuditDescription($event)
    {
        return "Income category {$event}: {$this->name}";
    }
}
