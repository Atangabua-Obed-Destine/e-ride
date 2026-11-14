<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One chat message: text, a photo, the buyer's delivery point (lat, lng, address in meta),
 * or the delivery the seller booked with E-Ride (trip id in meta).
 */
class MarketMessage extends Model
{
    use HasUuid;

    public const TYPES = ['text', 'photo', 'delivery_point', 'delivery_booked'];
    public const PHOTO_DIR = 'market/chat/';

    protected $fillable = ['conversation_id', 'sender_id', 'type', 'body', 'photo', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MarketConversation::class, 'conversation_id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? rtrim(dynamicStorage('storage/app/public/' . self::PHOTO_DIR), '/') . '/' . $this->photo : null;
    }
}
