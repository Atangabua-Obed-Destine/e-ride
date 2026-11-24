<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bus company's head office (Vatican Express) over its branches (bus agencies, one city each).
 * It watches every branch and chooses where the money goes and how E-Ride bills it.
 */
class BusCompany extends Model
{
    use HasUuid;

    public const ACTIVE = 'active';
    public const PAUSED = 'paused';
    /** money_mode and bill_mode */
    public const PER_BRANCH = 'branch';
    public const COMPANY = 'company';

    protected $fillable = [
        'name', 'slug', 'logo', 'about', 'status', 'money_mode', 'fapshi', 'fapshi_ok_at', 'webhook_code', 'bill_mode',
        'rules_template', 'turnaround_minutes',
    ];

    protected $casts = [
        'fapshi' => 'encrypted:array',
        'fapshi_ok_at' => 'datetime',
        'rules_template' => 'array',
        'turnaround_minutes' => 'integer',
    ];

    protected $hidden = ['fapshi'];

    public function branches(): HasMany
    {
        return $this->hasMany(BusAgency::class, 'company_id')->orderBy('origin_city')->orderBy('branch_label');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(BusCompanyStaff::class, 'company_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(BusVehicle::class, 'company_id');
    }

    /** Passengers pay one company account (not each branch's). */
    public function collectsMoney(): bool
    {
        return $this->money_mode === self::COMPANY;
    }

    /** E-Ride sends one statement for all branches. */
    public function billedTogether(): bool
    {
        return $this->bill_mode === self::COMPANY;
    }

    public function fapshiValues(): ?array
    {
        $f = $this->fapshi;
        return is_array($f) && filled($f['api_user'] ?? null) && filled($f['api_key'] ?? null) ? $f : null;
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? dynamicStorage('storage/app/public/bus/logo/' . $this->logo) : null;
    }
}
