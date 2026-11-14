<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something a shop sells. Live at once when posted; a banned word blocks it, reports
 * can hide it until an admin decides. Public (web and app) while live or sold out.
 */
class MarketItem extends Model
{
    use HasUuid, SoftDeletes;

    public const LIVE = 'live';
    public const HIDDEN = 'hidden';
    public const BLOCKED = 'blocked';
    public const REMOVED = 'removed';
    public const SOLD_OUT = 'sold_out';
    public const PUBLIC = [self::LIVE, self::SOLD_OUT];

    public const CONDITIONS = ['new', 'used', 'digital'];
    public const MAX_PHOTOS = 6;
    public const MAX_PREVIEWS = 3;

    protected $fillable = [
        'shop_id', 'code', 'title', 'description', 'price', 'negotiable', 'condition', 'digital_kind', 'category', 'quantity',
        'pickup', 'eride_delivery', 'seller_delivers', 'status', 'blocked_word', 'status_note', 'report_count', 'visits', 'chats',
    ];

    protected $casts = [
        'price' => 'float',
        'negotiable' => 'boolean',
        'quantity' => 'integer',
        'pickup' => 'boolean',
        'eride_delivery' => 'boolean',
        'seller_delivers' => 'boolean',
        'report_count' => 'integer',
        'visits' => 'integer',
        'chats' => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(MarketShop::class, 'shop_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MarketItemPhoto::class, 'item_id')->orderBy('sort');
    }

    public function isDigital(): bool
    {
        return $this->condition === 'digital';
    }

    public function isPublic(): bool
    {
        return in_array($this->status, self::PUBLIC, true) && $this->shop?->isLive();
    }

    /** The photo shown first (not a digital preview page when there is a cover). */
    public function cover(): ?MarketItemPhoto
    {
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();
        return $photos->firstWhere('preview', false) ?? $photos->first();
    }
}
