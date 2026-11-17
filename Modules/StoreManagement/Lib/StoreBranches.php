<?php

namespace Modules\StoreManagement\Lib;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Entities\StoreItemState;
use Modules\StoreManagement\Transformers\StorePresenter;
use Modules\ZoneManagement\Entities\Zone;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

/**
 * Which kitchen of a brand serves a customer, and why. The customer always
 * sees the reason and can choose another branch.
 *
 * Reasons: preferred (the branch they chose, open), preferred_closed (their
 * branch is closed or out of range, so the nearest open one), nearest_open,
 * nearest_closed (the nearest branch is closed, so the nearest open one),
 * only_open (the one open branch), all_closed (none open: browse only),
 * no_location (no address yet).
 */
class StoreBranches
{
    /**
     * @return array{kitchen: Store, reason: ?string, skipped: ?string, options: Collection}
     *         options: each ['kitchen' => Store, 'distance_km' => ?float, 'open' => bool, 'delivers' => bool], nearest first
     */
    public static function pick(Store $brand, ?float $lat, ?float $lng, ?string $preferredId = null, ?string $zoneId = null, ?Carbon $at = null): array
    {
        $at = $at ?? now();
        $kitchens = $brand->kitchens(true)->filter(fn(Store $k) => $k->zone_id && (!$zoneId || $k->zone_id === $zoneId))->values();
        if ($kitchens->isEmpty()) {
            $kitchens = collect([$brand]);
        }
        $options = $kitchens->map(fn(Store $k) => [
            'kitchen' => $k,
            'distance_km' => ($lat !== null && $lng !== null) ? round(StorePresenter::distanceKm($lat, $lng, $k->latitude, $k->longitude), 1) : null,
            'open' => $k->isOpenNow($at),
            'delivers' => self::delivers($k, $lat, $lng),
        ])->sortBy(fn($o) => $o['distance_km'] ?? 0)->values();

        $result = fn(Store $k, ?string $reason, ?Store $skipped = null) => [
            'kitchen' => $k, 'reason' => $options->count() > 1 ? $reason : null, 'skipped' => $skipped ? self::label($skipped) : null, 'options' => $options,
        ];
        $usable = fn($o) => $o['open'] && $o['delivers'];
        $open = $options->filter($usable)->values();

        $preferred = $preferredId ? $options->first(fn($o) => $o['kitchen']->id === $preferredId) : null;
        if ($preferred && $usable($preferred)) {
            return $result($preferred['kitchen'], 'preferred');
        }
        if ($lat === null || $lng === null) {
            return $result(($open->first() ?? $options->first())['kitchen'], 'no_location');
        }
        if ($open->isEmpty()) {
            $nearest = $options->first(fn($o) => $o['delivers']) ?? $options->first();
            return $result($nearest['kitchen'], 'all_closed');
        }
        $choice = $open->first()['kitchen'];
        if ($preferred) {
            return $result($choice, 'preferred_closed', $preferred['kitchen']);
        }
        $nearestDelivering = $options->first(fn($o) => $o['delivers']);
        if ($nearestDelivering && $nearestDelivering['kitchen']->id !== $choice->id) {
            return $result($choice, 'nearest_closed', $nearestDelivering['kitchen']);
        }
        if ($open->count() === 1) {
            return $result($choice, 'only_open');
        }
        return $result($choice, 'nearest_open');
    }

    /** Can this kitchen deliver to the point (same active zone)? True when the point is unknown. */
    public static function delivers(Store $kitchen, ?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) {
            return true;
        }
        if (!$kitchen->zone_id || !Zone::where('id', $kitchen->zone_id)->where('is_active', 1)->exists()) {
            return false;
        }
        return (bool)app(ZoneServiceInterface::class)->getZoneContainingBothPoints(
            $kitchen->zone_id, new Point($kitchen->latitude, $kitchen->longitude), new Point($lat, $lng));
    }

    /** "Mile 6" for a branch with a name, else the brand name. */
    public static function label(Store $kitchen): string
    {
        return $kitchen->branch_name ?: $kitchen->brand()->name;
    }

    /**
     * For dishes the chosen kitchen can't serve right now: the nearest other
     * branch that is open, delivers here and has the dish available now.
     *
     * @return callable(StoreItem): ?array{id: string, name: string, distance_km: ?float, more: int}
     */
    public static function elsewhereFinder(Collection $options, Store $chosen, ?Carbon $at = null): callable
    {
        $at = $at ?? now();
        $others = $options->filter(fn($o) => $o['kitchen']->id !== $chosen->id && $o['open'] && $o['delivers'])->values();
        if ($others->isEmpty()) {
            return fn() => null;
        }
        $states = StoreItemState::whereIn('store_id', $others->pluck('kitchen.id'))->get()->groupBy('store_id')->map(fn($g) => $g->keyBy('store_item_id'));
        return function (StoreItem $item) use ($others, $states, $at) {
            $where = $others->filter(fn($o) => $item->menuState($at, $states->get($o['kitchen']->id)?->get($item->id)) === StoreItem::AVAILABLE)->values();
            if ($where->isEmpty()) {
                return null;
            }
            $first = $where->first();
            return ['id' => $first['kitchen']->id, 'name' => self::label($first['kitchen']), 'distance_km' => $first['distance_km'], 'more' => $where->count() - 1];
        };
    }
}
