<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Modules\UserManagement\Entities\User;

/**
 * Someone who posts jobs on E-Ride Jobs: a business, or a person hiring for their home.
 * Checked once by E-Ride (a document only staff see, the workplace, a call), then posts freely.
 */
class JobEmployer extends Model
{
    use HasUuid;

    public const BUSINESS = 'business';
    public const PRIVATE = 'private';
    public const KINDS = [self::BUSINESS, self::PRIVATE];
    public const DOC_TYPES = ['licence', 'niu', 'id'];

    public const PENDING = 'pending';
    public const VERIFIED = 'verified';
    public const REJECTED = 'rejected';
    /** A scam: no more jobs, and the live ones are taken down. */
    public const FROZEN = 'frozen';

    public const LOGO_DIR = 'jobs/logo/';
    /** On the private disk (storage/app): only admins open these, through the admin panel. */
    public const DOC_DIR = 'jobs/doc/';

    protected $fillable = [
        'user_id', 'kind', 'name', 'about', 'logo', 'area_label', 'lat', 'lng', 'address', 'doc_type', 'doc_file',
        'status', 'review_note', 'reviewed_by', 'verified_at', 'jobs_posted',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'verified_at' => 'datetime',
        'jobs_posted' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(JobPost::class, 'employer_id');
    }

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }

    public function isPrivate(): bool
    {
        return $this->kind === self::PRIVATE;
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? rtrim(dynamicStorage('storage/app/public/' . self::LOGO_DIR), '/') . '/' . $this->logo : null;
    }

    public function docPath(): ?string
    {
        return $this->doc_file ? Storage::disk('local')->path(self::DOC_DIR . $this->doc_file) : null;
    }
}
