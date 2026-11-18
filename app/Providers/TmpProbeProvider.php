<?php
// TEMPORARY performance probe. Remove (and its line in bootstrap/providers.php) after measuring.
namespace App\Providers;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TmpProbeProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!request()->headers->has('X-Probe')) return;
        $n = 0; $ms = 0.0; $top = [];
        DB::listen(function ($q) use (&$n, &$ms, &$top) {
            $n++; $ms += $q->time;
            $k = substr(preg_replace('/\s+/', ' ', $q->sql), 0, 120);
            $top[$k] = ($top[$k] ?? 0) + 1;
        });
        Event::listen(RequestHandled::class, function (RequestHandled $e) use (&$n, &$ms, &$top) {
            arsort($top);
            $k = array_key_first($top);
            $e->response->headers->set('X-Queries', $n . ' q, ' . round($ms) . ' ms db, top ' . ($top[$k] ?? 0) . 'x ' . $k);
        });
    }
}
