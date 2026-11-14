<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** An item photo, or a page of a digital product's free preview. Its link-preview picture is made at upload. */
class MarketItemPhoto extends Model
{
    public const DIR = 'market/photo/';
    /** 1200x630 JPEG pictures WhatsApp and Facebook show in link previews. */
    public const OG_DIR = 'market/og/';

    protected $fillable = ['item_id', 'file', 'preview', 'sort'];

    protected $casts = ['preview' => 'boolean', 'sort' => 'integer'];

    public function url(): string
    {
        return rtrim(dynamicStorage('storage/app/public/' . self::DIR), '/') . '/' . $this->file;
    }

    public function ogFile(): string
    {
        return pathinfo($this->file, PATHINFO_FILENAME) . '.jpg';
    }

    public function ogUrl(): string
    {
        return rtrim(dynamicStorage('storage/app/public/' . self::OG_DIR), '/') . '/' . $this->ogFile();
    }
}
