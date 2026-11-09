<?php

namespace Modules\PlaceManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** A listing photo. "eride" ones were taken by staff on site, with where and when. */
class ListingPhoto extends Model
{
    public const DIR = 'listing/photo/';

    protected $fillable = ['listing_id', 'file', 'source', 'latitude', 'longitude', 'taken_at', 'sort'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'taken_at' => 'datetime',
        'sort' => 'integer',
    ];

    public function url(): string
    {
        // asset() inside dynamicStorage drops a trailing slash, so add it here.
        return rtrim(dynamicStorage('storage/app/public/' . self::DIR), '/') . '/' . $this->file;
    }

    public function byEride(): bool
    {
        return $this->source === 'eride';
    }
}
