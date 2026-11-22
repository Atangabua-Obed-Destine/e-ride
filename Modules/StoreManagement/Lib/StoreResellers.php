<?php

namespace Modules\StoreManagement\Lib;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StoreReseller;
use Modules\StoreManagement\Entities\StoreResellerPayout;
use Modules\UserManagement\Entities\User;

/**
 * Resellers of Food & Shops partners: people who do not own a shop but market others'.
 *
 * A partner sets a rate (a % of the items, never the delivery). A reseller shares the
 * store's link with their code ([domain]/f/SLUG?r=CODE). A customer who taps it and
 * orders from that store within some days (the last link tapped wins) brings the
 * reseller the rate on the items, once the order is delivered and paid. It comes out of
 * the store's earning; E-Ride pays resellers by Mobile Money, above a minimum.
 * No commission on the reseller's own orders, nor on the store owner's.
 */
class StoreResellers
{
    /** The store's rate for resellers, when it pays one (within the admin's limits), else null. */
    public static function rate(Store $store): ?float
    {
        $settings = StoreSettings::all();
        $pct = $store->brand()->reseller_pct;
        if (!$settings['reseller_status'] || $pct === null || (float)$pct <= 0) {
            return null;
        }
        return round(min($settings['reseller_max_percent'], max($settings['reseller_min_percent'], (float)$pct)), 2);
    }

    public static function mine(User $user): ?StoreReseller
    {
        return StoreReseller::where('user_id', $user->id)->first();
    }

    /** Join (or change the Mobile Money number). */
    public static function join(User $user, string $phone, string $name): StoreReseller
    {
        if (!StoreSettings::all()['reseller_status']) {
            self::fail('reseller', translate('Selling for shops is not open right now.'));
        }
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen(ltrim($phone, '+')) < 8) {
            self::fail('momo_phone', translate('Give the Mobile Money number you are paid on.'));
        }
        $reseller = self::mine($user);
        if ($reseller) {
            $reseller->update(['momo_phone' => $phone, 'momo_name' => trim($name)]);
            return $reseller->fresh();
        }
        return StoreReseller::create(['user_id' => $user->id, 'code' => self::newCode(), 'momo_phone' => $phone, 'momo_name' => trim($name),
            'status' => StoreReseller::ACTIVE]);
    }

    /** The link a reseller shares for a store. */
    public static function link(Store $store, StoreReseller $reseller): string
    {
        return StoreLinks::url($store) . '?r=' . $reseller->code;
    }

    /** Stores that pay resellers, for the "Sell for shops" list. */
    public static function stores(int $limit = 100): Collection
    {
        if (!StoreSettings::all()['reseller_status']) {
            return collect();
        }
        return Store::whereNull('parent_id')->where('is_active', 1)->whereNotNull('reseller_pct')->where('reseller_pct', '>', 0)
            ->orderByDesc('reseller_pct')->limit($limit)->get();
    }

    /** A customer opened a store through a reseller's link: counts for that store for some days. */
    public static function tap(User $customer, string $code, string $slug): bool
    {
        $reseller = StoreReseller::where('code', strtoupper(trim($code)))->first();
        $brand = Store::where('slug', $slug)->whereNull('parent_id')->first();
        if (!$reseller || !$reseller->isActive() || !$brand || $reseller->user_id === $customer->id
            || $brand->user_id === $customer->id || self::rate($brand) === null) {
            return false;
        }
        // One tap a day per customer and store is enough.
        $recent = DB::table('store_reseller_taps')->where('customer_id', $customer->id)->where('store_id', $brand->id)
            ->where('reseller_id', $reseller->id)->where('tapped_at', '>', now()->subHours(12))->exists();
        if (!$recent) {
            DB::table('store_reseller_taps')->insert(['reseller_id' => $reseller->id, 'customer_id' => $customer->id, 'store_id' => $brand->id, 'tapped_at' => now()]);
            StoreReseller::whereKey($reseller->id)->increment('taps');
        }
        return true;
    }

    /** A new order: the reseller whose link the customer tapped last for this store, within the window. */
    public static function attach(StoreOrder $order, Store $store): void
    {
        $brand = $store->brand();
        $rate = self::rate($brand);
        if ($rate === null) {
            return;
        }
        $tap = DB::table('store_reseller_taps')->where('customer_id', $order->customer_id)->where('store_id', $brand->id)
            ->where('tapped_at', '>', now()->subDays(StoreSettings::all()['reseller_window_days']))->orderByDesc('tapped_at')->first();
        if (!$tap) {
            return;
        }
        $reseller = StoreReseller::find($tap->reseller_id);
        if (!$reseller || !$reseller->isActive() || $reseller->user_id === $order->customer_id || $reseller->user_id === $brand->user_id) {
            return;
        }
        $order->reseller_id = $reseller->id;
        $order->reseller_pct = $rate;
    }

    /** The reseller's share of a delivered order: the rate on the items. */
    public static function commission(StoreOrder $order): float
    {
        if (!$order->reseller_id || !$order->reseller_pct) {
            return 0;
        }
        return round((float)$order->items_subtotal * (float)$order->reseller_pct / 100);
    }

    /** What a reseller brought and earned. */
    public static function summary(StoreReseller $reseller): array
    {
        $orders = StoreOrder::where('reseller_id', $reseller->id);
        $delivered = (clone $orders)->where('status', StoreOrder::DELIVERED);
        $earned = (float)(clone $delivered)->sum('reseller_commission');
        $paid = (float)StoreResellerPayout::where('reseller_id', $reseller->id)->sum('amount');
        return [
            'taps' => (int)$reseller->taps,
            'orders' => (clone $orders)->count(),
            'delivered' => (clone $delivered)->count(),
            'earned' => $earned,
            'paid' => $paid,
            'owed' => (float)(clone $delivered)->whereNull('reseller_payout_id')->sum('reseller_commission'),
            'min_payout' => StoreSettings::all()['reseller_min_payout'],
        ];
    }

    /** The reseller's recent orders: store, when, state, commission. Never who ordered. */
    public static function orders(StoreReseller $reseller, int $limit = 30): Collection
    {
        return StoreOrder::with('store')->where('reseller_id', $reseller->id)->latest('placed_at')->limit($limit)->get()
            ->map(fn(StoreOrder $o) => [
                'ref' => $o->ref_id,
                'store' => $o->store?->brand()->name,
                'placed_at' => $o->placed_at?->toIso8601String(),
                'status' => $o->status,
                'items_subtotal' => (float)$o->items_subtotal,
                'commission' => $o->status === StoreOrder::DELIVERED ? (float)$o->reseller_commission : round((float)$o->items_subtotal * (float)$o->reseller_pct / 100),
                'counted' => $o->status === StoreOrder::DELIVERED,
                'paid' => $o->reseller_payout_id !== null,
            ]);
    }

    /** Admin: resellers owed money (delivered, not paid yet), most first. */
    public static function owed(): Collection
    {
        $rows = StoreOrder::where('status', StoreOrder::DELIVERED)->whereNotNull('reseller_id')->whereNull('reseller_payout_id')
            ->where('reseller_commission', '>', 0)
            ->selectRaw('reseller_id, SUM(reseller_commission) AS owed, COUNT(*) AS n')->groupBy('reseller_id')->get()->keyBy('reseller_id');
        return StoreReseller::with('user')->whereIn('id', $rows->keys())->get()
            ->map(fn(StoreReseller $r) => ['reseller' => $r, 'owed' => (float)$rows[$r->id]->owed, 'orders' => (int)$rows[$r->id]->n])
            ->sortByDesc('owed')->values();
    }

    /** Admin sent the money: every delivered unpaid order of this reseller is covered. */
    public static function markPaid(StoreReseller $reseller, ?string $reference, ?string $adminId): StoreResellerPayout
    {
        return DB::transaction(function () use ($reseller, $reference, $adminId) {
            $orders = StoreOrder::where('reseller_id', $reseller->id)->where('status', StoreOrder::DELIVERED)->whereNull('reseller_payout_id')
                ->where('reseller_commission', '>', 0)->lockForUpdate()->get();
            if ($orders->isEmpty()) {
                self::fail('payout', translate('Nothing is owed to this reseller.'));
            }
            $payout = StoreResellerPayout::create(['reseller_id' => $reseller->id, 'amount' => (float)$orders->sum('reseller_commission'),
                'orders' => $orders->count(), 'reference' => $reference ? mb_substr(trim($reference), 0, 120) : null, 'paid_by' => $adminId, 'paid_at' => now()]);
            StoreOrder::whereIn('id', $orders->pluck('id'))->update(['reseller_payout_id' => $payout->id]);
            return $payout;
        });
    }

    private static function newCode(): string
    {
        do {
            $code = '';
            $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (StoreReseller::where('code', $code)->exists());
        return $code;
    }

    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
