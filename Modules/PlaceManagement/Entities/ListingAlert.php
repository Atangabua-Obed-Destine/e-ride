<?php

namespace Modules\PlaceManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** "Alert me": a seeker's saved search. A push goes out when a matching listing goes live. */
class ListingAlert extends Model
{
    public const MAX_PER_USER = 5;

    protected $fillable = ['user_id', 'type', 'neighbourhood', 'max_monthly_rent', 'verified_only', 'amenities'];

    protected $casts = [
        'max_monthly_rent' => 'float',
        'verified_only' => 'boolean',
        'amenities' => 'array',
    ];

    public function matches(Listing $listing): bool
    {
        return (!$this->type || $this->type === $listing->type)
            && (!$this->neighbourhood || strcasecmp($this->neighbourhood, $listing->neighbourhood) === 0)
            && (!$this->max_monthly_rent || $listing->monthlyRent() <= $this->max_monthly_rent)
            && (!$this->verified_only || $listing->isVerified())
            && $listing->hasAmenities($this->amenities ?? []);
    }
}
