<?php

namespace App\Ledger;

/**
 * Which business's books the ledger is working on.
 *
 * paxhitestbed has one school per install; PosInnova keeps many businesses in
 * one database. Every ledger model is scoped to Ledger::businessId(), which is
 * the business set with Ledger::forBusiness() (commands, observers, tests) or,
 * in a web request, the signed-in user's business. With neither, ledger
 * queries refuse to run rather than read every business's books.
 *
 * E-Ride: a "business" is one set of books, a row of ledger_businesses (a bus
 * branch, or a bus company's head office). App\Ledger\ERide\Books opens them and
 * sets the session keys read here.
 */
class Ledger
{
    private static $stack = [];

    private static $enabled = [];

    public static function businessId(): int
    {
        if (! empty(self::$stack)) {
            return end(self::$stack);
        }
        $id = null;
        try {
            $id = request()->hasSession() ? request()->session()->get('user.business_id') : null;
        } catch (\Throwable $e) {
            $id = null;
        }
        if (empty($id)) {
            throw new \LogicException('Ledger: no business selected (use Ledger::forBusiness()).');
        }

        return (int) $id;
    }

    public static function hasBusiness(): bool
    {
        try {
            self::businessId();

            return true;
        } catch (\LogicException $e) {
            return false;
        }
    }

    /** Run $fn with the books of $businessId. Nests; always restores the previous business. */
    public static function forBusiness(int $businessId, callable $fn)
    {
        self::$stack[] = $businessId;
        try {
            return $fn();
        } finally {
            array_pop(self::$stack);
        }
    }

    /** Is the accounting module switched on for this business (Settings › Modules › Accounting)? */
    public static function enabledFor(int $businessId): bool
    {
        if (! array_key_exists($businessId, self::$enabled)) {
            $mods = \DB::table('ledger_businesses')->where('id', $businessId)->value('enabled_modules');
            $mods = $mods ? (json_decode($mods, true) ?: []) : [];
            self::$enabled[$businessId] = in_array('accounting', $mods);
        }

        return self::$enabled[$businessId];
    }

    /** Has this business's chart of accounts been created (Set up the books)? */
    public static function isSetUp(int $businessId): bool
    {
        return \DB::table('chart_of_accounts')->where('business_id', $businessId)->exists();
    }

    /** An amount as the business writes money (its separators and decimals; FCFA has none). */
    public static function money($n): string
    {
        // E-Ride: FCFA, no decimals, a space between thousands.
        return number_format((float) $n, 0, ',', ' ');
    }

    public static function forgetEnabledCache(): void
    {
        self::$enabled = [];
    }
}
