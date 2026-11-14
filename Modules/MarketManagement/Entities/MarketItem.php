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
        'options', 'stock', 'wholesale_price', 'wholesale_min', 'orders',
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
        'options' => 'array',
        'stock' => 'array',
        'wholesale_price' => 'float',
        'wholesale_min' => 'integer',
        'orders' => 'integer',
    ];

    public const MAX_COLOURS = 12;
    public const MAX_SIZES = 20;

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

    /** @return array<int, array{name: string, photo_id: ?int}> */
    public function colours(): array
    {
        return array_values(array_filter((array)($this->options['colours'] ?? []), fn($c) => is_array($c) && ($c['name'] ?? '') !== ''));
    }

    /** @return string[] */
    public function sizes(): array
    {
        return array_values(array_filter(array_map('strval', (array)($this->options['sizes'] ?? [])), fn($s) => $s !== ''));
    }

    public function hasOptions(): bool
    {
        return $this->colours() !== [] || $this->sizes() !== [];
    }

    public static function stockKey(?string $colour, ?string $size): string
    {
        return trim((string)$colour) . '|' . trim((string)$size);
    }

    /** How many the seller has of one colour and size (the whole quantity for an item without options). */
    public function stockFor(?string $colour, ?string $size): int
    {
        if (!$this->hasOptions()) {
            return max(0, (int)$this->quantity);
        }
        return max(0, (int)(($this->stock ?? [])[self::stockKey($colour, $size)] ?? 0));
    }

    /** A buyer can put it in a cart: live, priced, and not a digital product (those are bought in the chat). */
    public function orderable(): bool
    {
        return $this->status === self::LIVE && $this->price !== null && $this->price > 0 && !$this->isDigital();
    }

    /** The price for one piece when the buyer takes $count pieces of this item in one order. */
    public function unitPriceFor(int $count): array
    {
        if ($this->wholesale_price !== null && $this->wholesale_min && $count >= $this->wholesale_min && $this->wholesale_price < $this->price) {
            return [(float)$this->wholesale_price, true];
        }
        return [(float)$this->price, false];
    }

    /** The photo shown first (not a digital preview page when there is a cover). */
    public function cover(): ?MarketItemPhoto
    {
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();
        return $photos->firstWhere('preview', false) ?? $photos->first();
    }
}
