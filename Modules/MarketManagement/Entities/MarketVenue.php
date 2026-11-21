<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A market E-Ride created (Bamenda Main Market...): shops list themselves under it with their shed. */
class MarketVenue extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'slug', 'city', 'area_label', 'about', 'latitude', 'longitude', 'entrances', 'blocks', 'buildings', 'is_active', 'sort'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'entrances' => 'array',
        'blocks' => 'array',
        'buildings' => 'array',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function shops(): HasMany
    {
        return $this->hasMany(MarketShop::class, 'venue_id');
    }

    /** The shops buyers see: live shops (not private sellers). */
    public function liveShops(): HasMany
    {
        return $this->shops()->where('status', MarketShop::LIVE)->where(fn($q) => $q->whereNull('kind')->orWhere('kind', MarketShop::KIND_SHOP));
    }

    /** @return array<int, array{name: string, lat: float, lng: float}> */
    public function entranceList(): array
    {
        $out = [];
        foreach ((array)($this->entrances ?? []) as $e) {
            if (is_array($e) && isset($e['lat'], $e['lng']) && is_numeric($e['lat']) && is_numeric($e['lng'])) {
                $out[] = ['name' => (string)($e['name'] ?? ''), 'lat' => (float)$e['lat'], 'lng' => (float)$e['lng']];
            }
        }
        return $out ?: [['name' => '', 'lat' => (float)$this->latitude, 'lng' => (float)$this->longitude]];
    }

    /** @return array<int, array{name: string, floors: int}> floors counts the ground floor (1 = ground only). */
    public function buildingList(): array
    {
        $out = [];
        foreach ((array)($this->buildings ?? []) as $b) {
            if (is_array($b) && trim((string)($b['name'] ?? '')) !== '') {
                $out[] = ['name' => trim((string)$b['name']), 'floors' => max(1, min(30, (int)($b['floors'] ?? 1)))];
            }
        }
        return $out;
    }

    /** @return string[] */
    public function blockList(): array
    {
        return array_values(array_filter(array_map(fn($b) => trim((string)$b), (array)($this->blocks ?? [])), fn($b) => $b !== ''));
    }
}
