<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\UserManagement\Entities\User;

/** A buyer's chat with a shop about one item. Only the buyer and the seller can read or write it. */
class MarketConversation extends Model
{
    use HasUuid;

    protected $fillable = [
        'item_id', 'shop_id', 'buyer_id', 'seller_id', 'last_message_at', 'last_message', 'buyer_unread', 'seller_unread', 'buyer_blocked',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'buyer_unread' => 'integer',
        'seller_unread' => 'integer',
        'buyer_blocked' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'item_id')->withTrashed();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(MarketShop::class, 'shop_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketMessage::class, 'conversation_id');
    }

    public function hasParty(string $userId): bool
    {
        return $userId === $this->buyer_id || $userId === $this->seller_id;
    }

    public function roleOf(string $userId): ?string
    {
        return $userId === $this->buyer_id ? 'buyer' : ($userId === $this->seller_id ? 'seller' : null);
    }
}
