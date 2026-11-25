<?php
/*
 * Shared start of the bus accounting test scripts (run from anywhere: php tests/BusAccounting/<name>.php).
 *
 * - The app runs with an in-memory cache: the scripts move the clock (Carbon::setTestNow) months ahead,
 *   and with the real cache the rate limiter and the sync locks would keep entries that far ahead.
 * - Every script works inside one database transaction that it rolls back at the end, and makes its
 *   own users, company and branches there, so it runs on any database (local or production) and
 *   leaves nothing behind.
 */
putenv('CACHE_DRIVER=array');
putenv('CACHE_STORE=array');
$_ENV['CACHE_DRIVER'] = $_SERVER['CACHE_DRIVER'] = 'array';
$_ENV['CACHE_STORE'] = $_SERVER['CACHE_STORE'] = 'array';

chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['cache.default' => 'array']);

/** A user made for the test, inside its transaction (partner by default, or customer). */
function accTestUser(string $name, string $type = 'partner'): Modules\UserManagement\Entities\User
{
    $email = Illuminate\Support\Str::slug($name) . '.' . Illuminate\Support\Str::lower(Illuminate\Support\Str::random(8)) . '@test.invalid';
    Modules\UserManagement\Entities\User::forceCreate([
        'first_name' => $name, 'last_name' => 'Test', 'email' => $email, 'phone' => '+2376' . random_int(10000000, 99999999),
        'password' => bcrypt(Illuminate\Support\Str::random(16)), 'user_type' => $type, 'is_active' => 1,
    ]);
    return Modules\UserManagement\Entities\User::where('email', $email)->firstOrFail();
}
