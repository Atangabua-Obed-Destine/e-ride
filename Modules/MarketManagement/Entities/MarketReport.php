<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** A buyer's report of an item or a shop. Scams are looked at first. */
class MarketReport extends Model
{
    use HasUuid;

    public const REASONS = ['scam', 'fake', 'banned', 'stolen', 'other'];
    public const OPEN = 'open';
    /** Admin decisions. */
    public const DECISIONS = ['kept', 'hidden', 'removed', 'closed'];

    protected $fillable = ['item_id', 'shop_id', 'reporter_id', 'reason', 'note', 'status', 'decided_by', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'item_id')->withTrashed();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(MarketShop::class, 'shop_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
