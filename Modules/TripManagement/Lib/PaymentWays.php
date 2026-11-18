<?php

namespace Modules\TripManagement\Lib;

/**
 * Ways to pay a ride or a parcel (Trip management > Ways to pay). Mobile Money
 * (the digital payment) is always on; cash and the E-Ride wallet can be turned
 * off. Both are on until the admin turns them off.
 *
 * Off means: the apps stop offering it, and the API refuses it for new trips
 * and payments. A Move is outside this: its price is agreed with the driver
 * and paid to them directly.
 */
class PaymentWays
{
    public static function cash(): bool
    {
        return (string)(businessConfig('payment_cash_status', TRIP_SETTINGS)?->value ?? '1') === '1';
    }

    public static function wallet(): bool
    {
        return (string)(businessConfig('payment_wallet_status', TRIP_SETTINGS)?->value ?? '1') === '1';
    }

    /** Whether a payment method may be used now: cash and wallet follow the switches, the rest are Mobile Money. */
    public static function allows(?string $method): bool
    {
        return match ($method) {
            'cash' => self::cash(),
            'wallet' => self::wallet(),
            default => true,
        };
    }

    /** The message when a method is turned off, in the customer's language. */
    public static function refusal(string $method): string
    {
        return $method === 'wallet'
            ? translate('Paying with the E-Ride wallet is not available. Please pay with Mobile Money.')
            : translate('Cash is not accepted. Please pay with Mobile Money.');
    }
}
