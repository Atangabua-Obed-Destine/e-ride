<?php

namespace Modules\HotelManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\HotelManagement\Lib\HotelSettings;
use Modules\PlaceManagement\Entities\Place;

/** A partner hotel or guest house: a verified place that runs the hotel portal. */
class Hotel extends Model
{
    use HasUuid;

    public const SETUP = 'setup';
    public const LIVE = 'live';
    public const PAUSED = 'paused';

    protected $fillable = [
        'place_id', 'name', 'status', 'pause_note', 'reception_phone', 'check_in_from', 'check_out_by',
        'answer_always', 'answer_from', 'answer_until', 'keep_max_hours', 'board_touched_at', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'answer_always' => 'boolean',
        'keep_max_hours' => 'integer',
        'board_touched_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(HotelStaff::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class)->orderBy('sort')->orderBy('price_from');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HotelRoom::class);
    }

    public function isLive(): bool
    {
        return $this->status === self::LIVE;
    }

    /** The reception touched its rooms recently enough for guests to see counts (linked to POS: POS checked in). */
    public function isFresh(): bool
    {
        if ($link = $this->posLink()) {
            return $link->isConnected();
        }
        return $this->board_touched_at !== null && $this->board_touched_at->gt(now()->subHours(HotelSettings::get('stale_hours')));
    }

    protected ?HotelPosLink $posLinkCache = null;
    protected bool $posLinkLoaded = false;

    /** Its live POS Innova link once POS has called (rooms then come from POS), else null. */
    public function posLink(): ?HotelPosLink
    {
        if (!$this->posLinkLoaded) {
            $this->posLinkCache = HotelPosLink::where('hotel_id', $this->id)->whereNull('revoked_at')->whereNotNull('linked_at')->latest()->first();
            $this->posLinkLoaded = true;
        }
        return $this->posLinkCache;
    }

    public function usesPos(): bool
    {
        return $this->posLink() !== null;
    }

    public function touchBoard(): void
    {
        $this->forceFill(['board_touched_at' => now()])->save();
    }
}
