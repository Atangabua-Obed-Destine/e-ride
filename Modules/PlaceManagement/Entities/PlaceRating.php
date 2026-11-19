<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * An E-Ride rider's rating of a place: stars and a few words. One per person
 * and place (rating again replaces it). `place_key` is the id the app knows
 * the place by: a verified place's uuid, or "g:" + its Google id, so places
 * that are only on Google can be rated too. Hidden ratings (moderation) count
 * for nothing.
 */
class PlaceRating extends Model
{
    use HasUuid;

    protected $fillable = ['place_key', 'place_name', 'user_id', 'stars', 'comment', 'is_visible', 'hidden_reason'];

    protected $casts = [
        'stars' => 'integer',
        'is_visible' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
