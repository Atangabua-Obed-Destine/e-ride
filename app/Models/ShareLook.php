<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/** How one partner's share pictures look (App\Lib\ShareLooks). */
class ShareLook extends Model
{
    use HasUuid;

    public const DESIGN_DIR = 'share-design/';

    protected $fillable = ['kind', 'owner_id', 'color', 'layout', 'item', 'items', 'design', 'design_w', 'design_h', 'strip',
        'design_at', 'removed_reason', 'removed_at', 'removed_by'];

    protected $casts = ['items' => 'array', 'strip' => 'boolean', 'design_at' => 'datetime', 'removed_at' => 'datetime',
        'design_w' => 'integer', 'design_h' => 'integer'];

    public function designUrl(): ?string
    {
        return $this->design ? rtrim(dynamicStorage('storage/app/public/' . self::DESIGN_DIR), '/') . '/' . $this->design : null;
    }
}
