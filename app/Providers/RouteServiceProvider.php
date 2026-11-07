<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';


    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        });

    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // The whole API, per caller. Keyed by the bearer token, not the IP: a Wi-Fi,
        // an office or a mobile carrier (carrier-grade NAT) puts many phones behind
        // one public IP. The per-IP limit is only a flood ceiling.
        RateLimiter::for('api', function (Request $request) {
            $limits = [Limit::perMinute(6000)->by('ip:' . $request->ip())];
            if ($token = $request->bearerToken()) {
                $limits[] = Limit::perMinute(1000)->by('token:' . sha1($token));
            }
            return $limits;
        });
    }
}
