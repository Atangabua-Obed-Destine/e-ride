<?php
/*
 * Opens every accounting page that takes no parameter, on a fresh bus branch's books, rolled back,
 * and lists the ones that fail. The screens of a stage must all open; the rest is the work left.
 * Run: php tests/Ledger/screens_scan.php
 */
require __DIR__ . '/../BusAccounting/_boot.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\{BusAgency, BusStaff};

DB::beginTransaction();
$bad = [];
try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $mgr = accTestUser('Scan Manager');
    BusAgency::forceCreate(['id' => (string)Str::uuid(), 'name' => 'Scan Branch', 'slug' => 'scan-' . Str::lower(Str::random(5)), 'origin_city' => 'Bamenda',
        'status' => 'live', 'webhook_code' => Str::lower(Str::random(16)), 'rules' => []]);
    $a = BusAgency::where('name', 'Scan Branch')->first();
    BusStaff::create(['agency_id' => $a->id, 'user_id' => $mgr->id, 'role' => 'manager', 'active' => true]);
    $session = $app['session']->driver();
    $call = function (string $method, string $url, array $data = []) use ($app, $kernel, $session, $mgr) {
        $req = Request::create($url, $method, $data, [], [], ['HTTP_ACCEPT' => 'text/html']);
        $app['auth']->guard('web')->setUser($mgr);
        $req->setLaravelSession($session);
        $session->put('_token', 'tok');
        $req->headers->set('X-CSRF-TOKEN', 'tok');
        return $kernel->handle($req);
    };
    $call('GET', '/partner/bus-agency/accounting');
    $call('POST', '/partner/accounting/setup', ['start' => 'today', 'reviewed' => 1]);
    $ready = \App\Ledger\ERide\Screens::ready();
    foreach (Route::getRoutes() as $r) {
        $name = (string)$r->getName();
        if (!str_starts_with($name, 'ledger.') || !in_array('GET', $r->methods(), true) || str_contains($r->uri(), '{')) {
            continue;
        }
        $res = $call('GET', '/' . $r->uri());
        $code = $res->getStatusCode();
        $tag = in_array($name, $ready, true) ? 'MENU' : '    ';
        if ($code >= 500) {
            $bad[] = $name;
            echo "$tag 500 $name: ", Str::limit((string)$res->exception?->getMessage(), 150), "\n";
        } else {
            echo "$tag $code $name\n";
        }
    }
} finally {
    DB::rollBack();
}
echo "\n", count($bad), " pages fail\n";
