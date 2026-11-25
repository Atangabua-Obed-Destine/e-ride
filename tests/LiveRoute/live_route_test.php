<?php
/*
 * Live trip routes (liveTripRoutes in app/Lib/Helpers.php): Google is faked, nothing is sent and
 * nothing is written to the database. Run from anywhere: php tests/LiveRoute/live_route_test.php
 */
putenv('CACHE_DRIVER=array');
putenv('CACHE_STORE=array');
$_ENV['CACHE_DRIVER'] = $_SERVER['CACHE_DRIVER'] = 'array';
$_ENV['CACHE_STORE'] = $_SERVER['CACHE_STORE'] = 'array';
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['cache.default' => 'array']);

use Illuminate\Support\Facades\Http;

$pass = 0;
$fail = 0;
function check(string $what, bool $ok): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
}

// A straight road north in Bamenda, about 2.2 km: 21 points 0.001 degrees apart.
$road = [];
for ($i = 0; $i <= 20; $i++) {
    $road[] = [5.9600 + $i * 0.001, 10.1500];
}
$polyline = encodePolyline($road);
$calls = [];
$googleDown = false;
Http::fake(function ($request) use (&$calls, &$googleDown, $polyline) {
    if ($googleDown) {
        return Http::response(['error' => ['code' => 400]], 400);
    }
    $calls[] = $request->data();
    return Http::response(['routes' => [[
        'distanceMeters' => 2200, 'duration' => '600s', 'staticDuration' => '600s',
        'polyline' => ['encodedPolyline' => $polyline],
    ]]], 200);
});

$dest = [5.9800, 10.1500];
$trip = 'test-' . uniqid();

// 1. First ask: Google once, without live traffic, and only the car route even for a motorbike.
$r = liveTripRoutes($trip, 'pickup', [5.9600, 10.1500], $dest, [], ['TWO_WHEELER']);
check('first ask calls Google once', count($calls) === 1);
check('no live traffic (Essentials tier)', ($calls[0]['routingPreference'] ?? '') === 'TRAFFIC_UNAWARE');
check('no two-wheeler call', !in_array('TWO_WHEELER', array_column($calls, 'travelMode'), true));
check('motorbike and car entries both returned', array_column($r, 'drive_mode') === ['TWO_WHEELER', 'DRIVE']);
check('motorbike is the car time / 1.2', $r[0]['duration_sec'] === 500 && $r[1]['duration_sec'] === 600);

// 2. Halfway along the road, polled 30 times by both apps: no new call, distance and time halve.
for ($i = 0; $i < 30; $i++) {
    $r = liveTripRoutes($trip, 'pickup', [5.9700, 10.1500], $dest, [], ['TWO_WHEELER']);
}
check('30 polls on the route: no new Google call', count($calls) === 1);
check('distance about half (1.1 km)', abs($r[1]['distance'] - 1.1) < 0.02);
check('time about half (300 s)', abs($r[1]['duration_sec'] - 300) <= 3);
check('motorbike time about half (250 s)', abs($r[0]['duration_sec'] - 250) <= 3);
check('distance text follows', $r[1]['distance_text'] === number_format($r[1]['distance'], 2) . ' km');
check('polyline starts at the driver', abs(decodePolyline($r[1]['encoded_polyline'])[0][0] - 5.9700) < 0.0001);

// 3. A minute later, off the route by about 300 m: one new call; again at once: none (30 s limit).
$now = Illuminate\Support\Carbon::now();
Illuminate\Support\Carbon::setTestNow($now->copy()->addMinute());
$r = liveTripRoutes($trip, 'pickup', [5.9700, 10.1530], $dest, [], ['DRIVE']);
check('off the route: Google asked again', count($calls) === 2);
liveTripRoutes($trip, 'pickup', [5.9705, 10.1540], $dest, [], ['DRIVE']);
check('still off the route right after: no new call', count($calls) === 2);

// 4. The next leg (the trip itself) is a new route; another trip does not share it.
liveTripRoutes($trip, 'trip', [5.9600, 10.1500], [5.9900, 10.1600], [], ['DRIVE']);
check('trip leg asks Google once', count($calls) === 3);
liveTripRoutes('other-' . uniqid(), 'pickup', [5.9600, 10.1500], $dest, [], ['DRIVE']);
check('another trip asks for its own', count($calls) === 4);

// 5. After the 30 seconds, off the route again: Google is asked again.
Illuminate\Support\Carbon::setTestNow($now->copy()->addMinutes(2));
liveTripRoutes($trip, 'pickup', [5.9700, 10.1560], $dest, [], ['DRIVE']);
check('after 30 s off the route: Google again', count($calls) === 5);

// 6. Google fails: the error comes back and nothing is stored.
$googleDown = true;
$r = liveTripRoutes('fail-' . uniqid(), 'pickup', [5.9600, 10.1500], $dest, [], ['DRIVE']);
check('Google error passes through', isset($r['error']));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
