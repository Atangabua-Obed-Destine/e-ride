<?php

namespace Modules\StoreManagement\Lib;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StoreRider;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;
use Throwable;

/**
 * "My riders": the E-Ride riders a kitchen chose for its deliveries.
 *
 * The store adds a rider who already delivered for it, or one it knows by
 * their E-Ride phone number; it never browses every rider. The rider is told,
 * is offered that store's deliveries before anyone else, and can leave.
 */
class StoreRiders
{
    public const MAX = 30;

    /** Current riders of a kitchen who can still drive (active accounts). */
    public static function ids(Store $store): array
    {
        return StoreRider::where('store_id', $store->id)->where('status', StoreRider::ACTIVE)
            ->whereHas('driver', fn($q) => $q->where('is_active', 1))
            ->pluck('driver_id')->map(fn($id) => (string)$id)->all();
    }

    /**
     * Who a new delivery of this order is held for: ['ids', 'until', 'mode'],
     * or null when it rings every rider (no list, feature off, or the store
     * opened this order to everyone).
     */
    public static function reservation(StoreOrder $order): ?array
    {
        $store = $order->store;
        $mode = $store?->ridersMode() ?? 'all';
        if ($mode === 'all' || $order->riders_open) {
            return null;
        }
        $ids = self::ids($store);
        if (empty($ids)) {
            return null;
        }
        return [
            'ids' => $ids,
            'mode' => $mode,
            'until' => $mode === 'first' ? now()->addSeconds(StoreSettings::all()['my_riders_first_seconds']) : null,
        ];
    }

    /** Deliveries each rider completed for this kitchen: [driver_id => ['count', 'last']]. */
    protected static function deliveries(Store $store): \Illuminate\Support\Collection
    {
        $trips = StoreOrder::where('store_id', $store->id)->where('status', StoreOrder::DELIVERED)->whereNotNull('trip_request_id')->select('trip_request_id');
        return TripRequest::whereIn('id', $trips)->whereNotNull('driver_id')
            ->selectRaw('driver_id, COUNT(*) AS deliveries, MAX(updated_at) AS last_at')
            ->groupBy('driver_id')->get()->keyBy('driver_id');
    }

    protected static function card(User $driver, $done): array
    {
        $details = $driver->driverDetails;
        return [
            'driver_id' => $driver->id,
            'name' => trim($driver->first_name . ' ' . $driver->last_name),
            'phone' => $driver->phone,
            'image' => $driver->profile_image ? dynamicStorage('storage/app/public/driver/profile') . '/' . $driver->profile_image : null,
            'rating' => $driver->received_reviews_avg_rating !== null ? round((float)$driver->received_reviews_avg_rating, 1) : null,
            'deliveries' => (int)($done->deliveries ?? 0),
            'online' => (int)($details->is_online ?? 0) === 1 && $driver->is_active,
        ];
    }

    /** The kitchen's riders, the ones who deliver for it most first. */
    public static function list(Store $store): array
    {
        $done = self::deliveries($store);
        return StoreRider::with(['driver' => fn($q) => $q->with('driverDetails')->withAvg('receivedReviews', 'rating')])
            ->where('store_id', $store->id)->where('status', StoreRider::ACTIVE)->get()
            ->filter(fn(StoreRider $r) => $r->driver !== null)
            ->map(fn(StoreRider $r) => self::card($r->driver, $done->get($r->driver_id)) + ['since' => $r->updated_at?->toIso8601String()])
            ->sortByDesc('deliveries')->values()->all();
    }

    /** Riders who delivered for this kitchen and are not on its list yet. */
    public static function candidates(Store $store, int $limit = 12): array
    {
        $done = self::deliveries($store);
        $mine = self::ids($store);
        $ids = $done->keys()->reject(fn($id) => in_array((string)$id, $mine, true))->values();
        if ($ids->isEmpty()) {
            return [];
        }
        return User::with('driverDetails')->withAvg('receivedReviews', 'rating')
            ->whereIn('id', $ids)->where('user_type', DRIVER)->where('is_active', 1)->get()
            ->map(fn(User $d) => self::card($d, $done->get($d->id)))
            ->sortByDesc('deliveries')->take($limit)->values()->all();
    }

    /**
     * @throws ValidationException when it is not an active rider, or the list is full
     */
    public static function add(Store $store, ?User $driver): StoreRider
    {
        if (!$driver || $driver->user_type !== DRIVER || !$driver->is_active) {
            throw ValidationException::withMessages(['rider' => translate('No active E-Ride rider was found. Check the number with the rider.')]);
        }
        $row = StoreRider::where('store_id', $store->id)->where('driver_id', $driver->id)->first();
        if ($row?->status === StoreRider::ACTIVE) {
            return $row;
        }
        if (StoreRider::where('store_id', $store->id)->where('status', StoreRider::ACTIVE)->count() >= self::MAX) {
            throw ValidationException::withMessages(['rider' => translate(key: 'You can have up to {max} riders. Remove one first.', replace: ['max' => self::MAX])]);
        }
        $row = $row ?? new StoreRider(['store_id' => $store->id, 'driver_id' => $driver->id]);
        $row->status = StoreRider::ACTIVE;
        $row->ended_at = null;
        $row->save();
        self::tell($driver, $store, true);
        return $row;
    }

    /**
     * By the number the rider uses on E-Ride, typed as people write it
     * ("6 71 00 84 93", "+237671008493").
     *
     * @throws ValidationException
     */
    public static function addByPhone(Store $store, string $phone): StoreRider
    {
        $digits = preg_replace('/\D+/', '', $phone);
        $forms = array_values(array_unique(array_filter([
            $digits !== '' ? '+' . $digits : null,
            strlen($digits) === 9 ? '+237' . $digits : null,
            $digits,
        ])));
        $driver = $forms ? User::where('user_type', DRIVER)->whereIn('phone', $forms)->first() : null;
        return self::add($store, $driver);
    }

    public static function remove(Store $store, string $driverId): void
    {
        $row = StoreRider::where('store_id', $store->id)->where('driver_id', $driverId)->where('status', StoreRider::ACTIVE)->first();
        if (!$row) {
            return;
        }
        $row->update(['status' => StoreRider::REMOVED, 'ended_at' => now()]);
        $row->driver && self::tell($row->driver, $store, false);
    }

    /** The stores a rider delivers for, for the driver app. */
    public static function forDriver(User $driver): array
    {
        return StoreRider::with('store.parent')->where('driver_id', $driver->id)->where('status', StoreRider::ACTIVE)->get()
            ->filter(fn(StoreRider $r) => $r->store && !$r->store->trashed())
            ->map(fn(StoreRider $r) => [
                'id' => $r->id,
                'store_id' => $r->store_id,
                'name' => $r->store->displayName(),
                'type' => $r->store->brand()->type,
                'address' => $r->store->address,
                'logo' => $r->store->logoUrl(),
                'phone' => $r->store->phone,
                'since' => $r->updated_at?->toIso8601String(),
                // first: its deliveries reach this rider before the others; only: nobody else gets them.
                'mode' => $r->store->ridersMode(),
            ])->values()->all();
    }

    /** The rider leaves a store's list. False when it was not theirs. */
    public static function leave(User $driver, string $id): bool
    {
        $row = StoreRider::where('id', $id)->where('driver_id', $driver->id)->where('status', StoreRider::ACTIVE)->first();
        if (!$row) {
            return false;
        }
        $row->update(['status' => StoreRider::LEFT, 'ended_at' => now()]);
        return true;
    }

    /** Push to the rider: added to, or removed from, a store's riders. */
    protected static function tell(User $driver, Store $store, bool $added): void
    {
        try {
            if (!$driver->fcm_token) {
                return;
            }
            $locale = $driver->current_language_key;
            $name = $store->displayName();
            sendDeviceNotification(
                fcm_token: $driver->fcm_token,
                title: $added
                    ? translate(key: '{store} added you to its riders', replace: ['store' => $name], locale: $locale)
                    : translate(key: '{store} removed you from its riders', replace: ['store' => $name], locale: $locale),
                description: $added
                    ? translate(key: 'You will be offered its deliveries before other riders. Stay online near the shop.', locale: $locale)
                    : translate(key: 'You still get its deliveries like every other rider.', locale: $locale),
                status: 1,
                type: 'store_team',
                notification_type: 'store_team',
                action: $added ? 'store_team_added' : 'store_team_removed',
                user_id: $driver->id,
                notificationData: ['extra' => ['store_id' => $store->id]],
            );
        } catch (Throwable $e) {
            Log::info('Food & Shops: rider not told about the store list', ['driver' => $driver->id, 'error' => $e->getMessage()]);
        }
    }
}
