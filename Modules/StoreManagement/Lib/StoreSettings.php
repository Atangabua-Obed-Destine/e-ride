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
        ];
    }

    public static function enabled(): bool
    {
        return self::all()['status'];
    }
}
