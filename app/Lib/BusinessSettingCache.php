<?php

namespace App\Lib;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionRolledBack;
use Modules\BusinessManagement\Entities\BusinessSetting;

/**
 * Every business setting, read in one query and kept for the request.
 *
 * businessConfig() used to run one query per lookup: the customer
 * /configuration call alone ran 258 of them, about half a second on the
 * production database. The table is small (about a hundred rows).
 *
 * Kept correct by: any write to business_settings in this process clears it,
 * as does a rolled back transaction; a long-running process (queue worker,
 * scheduler) re-reads after a few seconds; a new application instance (each
 * test) starts empty.
 */
class BusinessSettingCache
{
    private const TTL_SECONDS = 10;

    /** @var array<string, BusinessSetting>|null keyed "key\0type" */
    private static ?array $byKeyAndType = null;

    /** @var array<string, BusinessSetting>|null keyed "key": the first row with that key */
    private static ?array $byKey = null;

    private static float $loadedAt = 0;
    private static ?object $events = null;

    public static function find(string $key, ?string $settingsType = null): ?BusinessSetting
    {
        self::load();
        return $settingsType === null
            ? (self::$byKey[$key] ?? null)
            : (self::$byKeyAndType[$key . "\0" . $settingsType] ?? null);
    }

    public static function flush(): void
    {
        self::$byKeyAndType = null;
        self::$byKey = null;
    }

    private static function load(): void
    {
        self::watch();
        if (self::$byKeyAndType !== null && microtime(true) - self::$loadedAt < self::TTL_SECONDS) {
            return;
        }
        $byKeyAndType = [];
        $byKey = [];
        foreach (BusinessSetting::query()->get() as $row) {
            $byKeyAndType[$row->key_name . "\0" . $row->settings_type] ??= $row;
            $byKey[$row->key_name] ??= $row;
        }
        self::$byKeyAndType = $byKeyAndType;
        self::$byKey = $byKey;
        self::$loadedAt = microtime(true);
    }

    /** Listens for writes once per application instance. */
    private static function watch(): void
    {
        $events = app('events');
        if (self::$events === $events) {
            return;
        }
        self::$events = $events;
        self::flush();
        $events->listen(QueryExecuted::class, function (QueryExecuted $query) {
            if (self::$byKeyAndType !== null
                && stripos($query->sql, 'business_settings') !== false
                && !preg_match('/^\s*select\b/i', $query->sql)) {
                self::flush();
            }
        });
        $events->listen(TransactionRolledBack::class, fn () => self::flush());
    }
}
