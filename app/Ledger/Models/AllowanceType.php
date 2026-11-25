<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** paxhitestbed AllowanceType: the allowances a payroll may carry, and whether each is taxed. */
class AllowanceType extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'allowance_types';

    protected $fillable = ['business_id', 'title', 'description', 'is_taxable', 'status'];

    protected $casts = ['is_taxable' => 'boolean', 'status' => 'boolean'];
}
