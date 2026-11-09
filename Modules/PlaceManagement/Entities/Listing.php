<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\PlaceManagement\Lib\ListingSettings;
use Modules\UserManagement\Entities\User;

/**
 * A place to rent that a customer listed (Homes & spaces). Public only while
 * live (or paused, never); "Listed" once E-Ride reviewed it, "E-Ride Verified"
 * while a staff visit is recent. The owner confirms it is still available on
 * a schedule that depends on the type: see ListingSettings.
 */
class Listing extends Model
{
    use HasUuid, SoftDeletes;

    public const TYPES = ['hostel_room', 'room', 'studio', 'apartment', 'office', 'shop'];
    /** Turn over fast (students): confirmed more often. */
    public const SHORT_TYPES = ['hostel_room', 'room', 'studio'];
    public const WATER = ['tap', 'borehole', 'well', 'none'];
    public const POWER = ['prepaid_meter', 'shared_meter', 'none'];
    /**
     * Extras an owner can tick, in display order. Water, light, backup power, toilet,
     * kitchen, fence and security guard have their own fields. The app has the labels and icons.
     */
    public const AMENITIES = [
        'wifi', 'cctv', 'water_tank', 'parking', 'tarred_road', 'tiled_floor', 'wardrobe',
        'study_area', 'hot_water', 'laundry_area', 'cleaning', 'air_conditioning',
    ];

    /** Admin labels for AMENITIES. */
    public static function amenityLabels(): array
    {
        return [
            'wifi' => translate('Internet (WiFi)'),
            'cctv' => translate('CCTV cameras'),
            'water_tank' => translate('Water storage tank'),
            'parking' => translate('Parking'),
            'tarred_road' => translate('Tarred road to the gate'),
            'tiled_floor' => translate('Tiled floor'),
            'wardrobe' => translate('Wardrobe'),
            'study_area' => translate('Study or reading room'),
            'hot_water' => translate('Hot water'),
            'laundry_area' => translate('Laundry area'),
            'cleaning' => translate('Shared areas cleaned'),
            'air_conditioning' => translate('Air conditioning'),
        ];
    }

    /** "wifi, cctv" or a list, to the known keys in display order. */
    public static function cleanAmenities(string|array|null $value): array
    {
        $given = is_array($value) ? $value : explode(',', (string)$value);
        $given = array_map(fn($v) => trim((string)$v), $given);
        return array_values(array_filter(self::AMENITIES, fn($key) => in_array($key, $given, true)));
    }

    /** @param string[] $required */
    public function hasAmenities(array $required): bool
    {
        return !array_diff($required, $this->amenities ?? []);
    }

    public const PENDING = 'pending';
    public const CHANGES = 'changes';
    public const LIVE = 'live';
    public const PAUSED = 'paused';
    public const RENTED = 'rented';
    public const REFUSED = 'refused';
    public const REMOVED = 'removed';

    public const MIN_PHOTOS = 4;
    public const MAX_PHOTOS = 10;

    protected $fillable = [
        'owner_id', 'type', 'title', 'description', 'neighbourhood', 'address', 'latitude', 'longitude', 'zone_id',
        'units_available', 'furnished', 'rent', 'rent_period', 'advance_months', 'caution', 'available_from',
        'water', 'power', 'backup_power', 'toilet', 'kitchen', 'fenced', 'security', 'amenities', 'reservations_on', 'reservation_amount', 'payout_phone', 'payout_name', 'owner_role',
        'status', 'review_note', 'needs_review', 'approved_at', 'last_confirmed_at', 'reminder_stage', 'paused_at',
        'taken_check_until', 'verified_until', 'verified_expiry_notified_at', 'stale_nudged_at', 'rented_at', 'views',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'rent' => 'float',
        'caution' => 'float',
        'units_available' => 'integer',
        'advance_months' => 'integer',
        'furnished' => 'boolean',
        'backup_power' => 'boolean',
        'fenced' => 'boolean',
        'security' => 'boolean',
        'amenities' => 'array',
        'reservations_on' => 'boolean',
        'reservation_amount' => 'float',
        'needs_review' => 'boolean',
        'available_from' => 'date',
        'approved_at' => 'datetime',
        'last_confirmed_at' => 'datetime',
        'paused_at' => 'datetime',
        'taken_check_until' => 'datetime',
        'verified_until' => 'datetime',
        'verified_expiry_notified_at' => 'datetime',
        'stale_nudged_at' => 'datetime',
        'rented_at' => 'datetime',
        'reminder_stage' => 'integer',
        'views' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderByRaw("source = 'eride' desc")->orderBy('sort')->orderBy('id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ListingVisit::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(ListingVerification::class)->latest();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class)->latest();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ListingReservation::class)->latest();
    }

    /** Seekers can reserve this place through E-Ride now (the feature on, the owner opted in, a unit free). */
    public function takesReservations(): bool
    {
        if (!ListingSettings::reservations()['enabled'] || !$this->reservations_on || !$this->reservation_amount || $this->status !== self::LIVE) {
            return false;
        }
        $pending = $this->reservations()->whereIn('status', [ListingReservation::AWAITING, ListingReservation::HELD])->count();
        return $this->units_available - $pending > 0;
    }

    /** Days between two confirmations of "still available". */
    public function confirmEveryDays(): int
    {
        $s = ListingSettings::all();
        return in_array($this->type, self::SHORT_TYPES, true) ? $s['confirm_days_short'] : $s['confirm_days_long'];
    }

    public function confirmDueAt(): ?Carbon
    {
        return $this->last_confirmed_at?->copy()->addDays($this->confirmEveryDays());
    }

    /** Badge "Not confirmed recently": the confirmation is a few days late. */
    public function isLate(?Carbon $now = null): bool
    {
        $due = $this->confirmDueAt();
        return $this->status === self::LIVE && $due && ($now ?? now())->gt($due->copy()->addDays(ListingSettings::all()['late_after_days']));
    }

    public function isVerified(?Carbon $now = null): bool
    {
        return $this->verified_until && $this->verified_until->gt($now ?? now());
    }

    /** Rent per month, to compare a yearly rent with a monthly one. */
    public function monthlyRent(): float
    {
        return $this->rent_period === 'year' ? round($this->rent / 12, 2) : (float)$this->rent;
    }
}
