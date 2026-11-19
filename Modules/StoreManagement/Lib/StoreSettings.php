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
