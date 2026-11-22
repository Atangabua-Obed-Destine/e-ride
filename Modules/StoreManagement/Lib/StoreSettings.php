<?php

namespace Modules\StoreManagement\Lib;

/** Food & Shops settings (Business Setup > Food & Shops), with defaults. */
class StoreSettings
{
    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, STORE_SETTINGS)?->value ?? $default;
        return [
            'status' => (string)$get('store_ordering_status', '0') === '1',
            'service_label' => (string)$get('store_service_label', 'Food & Shops'),
            'partner_response_minutes' => (int)$get('store_partner_response_minutes', 5),
            'rider_lead_minutes' => (int)$get('store_rider_lead_minutes', 10),
            'parcel_weight' => (float)$get('store_order_parcel_weight', 1),
            'parcel_category_id' => $get('store_order_parcel_category_id', null),
            // Off by default: paid food orders are not refunded (see refunds()).
            'refund_status' => (string)$get('store_refund_status', '0') === '1',
            // "The shop brings it": a store's own staff deliver close by.
            'own_delivery_status' => (string)$get('store_own_delivery_status', '1') === '1',
            'own_delivery_max_km' => max(0.1, (float)$get('store_own_delivery_max_km', 1)),
            // E-Ride's part of the store's own delivery fee, in percent (0 = the store keeps it all).
            'own_delivery_share_percent' => min(100, max(0, (float)$get('store_own_delivery_share_percent', 0))),
            // "My riders": a store's deliveries ring the riders it chose first, or only them.
            'my_riders_status' => (string)$get('store_my_riders_status', '1') === '1',
            'my_riders_first_seconds' => max(20, (int)$get('store_my_riders_first_seconds', 60)),
            // Resellers: people who share a store's link and earn a % of the items of the orders it brings.
            'reseller_status' => (string)$get('store_reseller_status', '1') === '1',
            'reseller_min_percent' => min(100, max(0, (float)$get('store_reseller_min_percent', 2))),
            'reseller_max_percent' => min(100, max(1, (float)$get('store_reseller_max_percent', 30))),
            // Days a tap on a reseller's link counts for that store (the last link tapped wins).
            'reseller_window_days' => min(60, max(1, (int)$get('store_reseller_window_days', 7))),
            // E-Ride pays a reseller once they are owed at least this much.
            'reseller_min_payout' => max(0, (float)$get('store_reseller_min_payout', 1000)),
        ];
    }

    /**
     * Whether a cancelled paid order is refunded. Refunds go to the E-Ride
     * wallet, so they need the admin's wallet switch on too. When off, nothing
     * is credited and no refund is mentioned anywhere: E-Ride's team settles
     * with the customer (the admin is alerted).
     */
    public static function refunds(): bool
    {
        return self::all()['refund_status'] && \Modules\TripManagement\Lib\PaymentWays::wallet();
    }

    public static function enabled(): bool
    {
        return self::all()['status'];
    }
}
