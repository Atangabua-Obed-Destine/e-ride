<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * paxhitestbed StaffTaxExemption: one staff member, one tax. A custom rate (percentage tax) or
 * amount (fixed tax) replaces the normal one; none means fully exempt; the employer side is zero
 * while exempt; an expired exemption no longer counts.
 */
class StaffTaxExemption extends Model
{
    use BelongsToBusiness;

    protected $table = 'staff_tax_exemptions';

    protected $fillable = ['business_id', 'staff_id', 'tax_setting_id', 'reason', 'custom_percentage', 'custom_fixed_amount', 'expires_at'];

    protected $casts = ['expires_at' => 'date', 'custom_percentage' => 'float', 'custom_fixed_amount' => 'float'];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function taxSetting()
    {
        return $this->belongsTo(TaxSetting::class, 'tax_setting_id');
    }

    public function scopeNotExpired($query, $date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();

        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $date));
    }

    public function isExpired($date = null)
    {
        if (is_null($this->expires_at)) {
            return false;
        }

        return $this->expires_at < ($date ? Carbon::parse($date) : Carbon::today());
    }

    public function isActive($date = null)
    {
        return ! $this->isExpired($date);
    }
}
