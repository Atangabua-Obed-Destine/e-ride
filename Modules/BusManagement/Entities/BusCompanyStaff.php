<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * Someone at a bus company's head office: the director (everything), a regional manager (only their
 * branches, no money or settings) or an accountant (reports and statements, changes nothing).
 */
class BusCompanyStaff extends Model
{
    public const DIRECTOR = 'director';
    public const REGIONAL = 'regional';
    public const ACCOUNTANT = 'accountant';
    public const ROLES = [self::DIRECTOR, self::REGIONAL, self::ACCOUNTANT];

    protected $table = 'bus_company_staff';

    protected $fillable = ['company_id', 'user_id', 'role', 'agency_ids', 'active'];

    protected $casts = ['agency_ids' => 'array', 'active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusCompany::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Can change things (pause a branch, staff, fares, rules). The accountant only reads. */
    public function canAct(): bool
    {
        return in_array($this->role, [self::DIRECTOR, self::REGIONAL], true);
    }

    public function isDirector(): bool
    {
        return $this->role === self::DIRECTOR;
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            self::DIRECTOR => translate('Director'),
            self::REGIONAL => translate('Regional manager'),
            default => translate('Accountant'),
        };
    }
}
