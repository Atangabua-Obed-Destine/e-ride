<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** paxhitestbed DeductionType: the deductions a payroll may carry. */
class DeductionType extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'deduction_types';

    protected $fillable = ['business_id', 'title', 'description', 'status'];

    protected $casts = ['status' => 'boolean'];
}
