<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An expense category (paxhitestbed ExpenseCategory). Its ledger account is the
 * expense_category mapping keyed by its id (Mapping settings), '' = any other.
 * Expenses and supplier bill lines are filed under it.
 */
class ExpenseCategory extends Model
{
    use SoftDeletes, Auditable, BelongsToBusiness;

    protected $table = 'expense_categories';

    protected $fillable = ['business_id', 'name', 'slug', 'parent_id', 'description', 'status', 'created_by'];

    protected $casts = ['status' => 'boolean'];

    public function getTitleAttribute()
    {
        return $this->name;
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function getAuditDescription($event)
    {
        return "Expense category {$event}: {$this->name}";
    }
}
