<?php

namespace Modules\PlaceManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** A Google lodging result an admin removed from the app (closed, wrong type, duplicate). */
class GoogleHiddenPlace extends Model
{
    protected $table = 'place_google_hidden';

    protected $fillable = ['google_place_id', 'label', 'reason', 'created_by'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
