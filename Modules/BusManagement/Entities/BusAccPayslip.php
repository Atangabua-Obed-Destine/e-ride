<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's pay for one month: gross, each withholding, net, and what the employer adds. */
class BusAccPayslip extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'bus_acc_payslips';

    protected $fillable = ['agency_id', 'document_id', 'employee_id', 'month', 'base', 'extra', 'gross', 'cnps', 'irpp', 'cac', 'cfc', 'crtv', 'tdl', 'net',
        'employer_cnps', 'employer_taxes', 'detail'];

    protected $casts = ['month' => 'date', 'detail' => 'array', 'base' => 'float', 'extra' => 'float', 'gross' => 'float', 'cnps' => 'float', 'irpp' => 'float',
        'cac' => 'float', 'cfc' => 'float', 'crtv' => 'float', 'tdl' => 'float', 'net' => 'float', 'employer_cnps' => 'float', 'employer_taxes' => 'float'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(BusAccEmployee::class, 'employee_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BusAccDocument::class, 'document_id');
    }
}
