<?php

use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use App\Exceptions\ImageUploadException;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\{Authenticate,
    EncryptCookies,
    GlobalMiddleware,
    Localization,
    LocalizationMiddleware,
    MaintenanceModeMiddleware,
    RedirectIfAuthenticated,
    VerifyCsrfToken};
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\AdminModule\Http\Middleware\AdminMiddleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
//            TrustHosts::class,
            TrustProxies::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            GlobalMiddleware::class,
        ]);
        $middleware->group('web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
//            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            Localization::class
        ]);
        $middleware->group('api', [
//           EnsureFrontendRequestsAreStateful::class,
            // Named limiter in RouteServiceProvider. Route-level throttles pass a key
            // prefix (throttle:N,1,name) so each keeps its own counter.
            'throttle:api',
            SubstituteBindings::class,
            LocalizationMiddleware::class
        ]);
        /*
        |--------------------------------------------------------------------------
        | Route Middleware (Aliases)
        |--------------------------------------------------------------------------
        */
        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.basic' => AuthenticateWithBasicAuth::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.confirm' => RequirePassword::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,

            // Custom middlewares
            'admin'=>AdminMiddleware::class,
            'maintenance_mode' => MaintenanceModeMiddleware::class,
            // One change at a time per trip (accept, cancel, start, complete, pay).
            'trip.lock' => \App\Http\Middleware\TripStateLock::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ImageUploadException $e, $request) {
            if ($request->wantsJson()) {
                return response()->json(responseFormatter(IMAGE_UPLOAD_FAILED_422), 403);
            }

            Toastr::error($e->getMessage());

            return back()->withInput();
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->wantsJson()) {
                return response()->json(responseFormatter(DEFAULT_404), 404);
            }
        });

        $exceptions->render(function (HttpException $e, $request) {
            if ($request->wantsJson()) {
                return response()->json([
                    'response_code' => $e->getStatusCode(),
                    'message' => $e->getMessage(),
                    'content' => null,
                    'errors' => [],
                ], $e->getStatusCode());
            }
        });
    })
    // Laravel 12 never loads app/Console/Kernel.php, so its schedule() was dead code. Keep this list in sync with it.
    ->withSchedule(function (Schedule $schedule) {
        // Each job runs inside the scheduler's own process. As separate
        // `php artisan` processes, every one loaded the whole application
        // first: about twenty start-ups a minute on a one-core server, taking
        // the CPU from customers' requests. A failing job is reported and the
        // others still run, as before.
        $job = fn (string $command) => $schedule
            ->call(fn () => \Illuminate\Support\Facades\Artisan::call($command))
            ->name($command)
            // A run killed midway (restart, out of memory) must not stop the job for a day.
            ->withoutOverlapping(10);

        $job('trip-request:cancel')->everyMinute();
        $job('app:process-scheduled-trips')->everyMinute();
        $job('driver:resume-paused')->everyMinute();
        $job('driver:sync-availability')->everyMinute();
        $job('app:process-late-return-penalty-notifications')->everyMinute();
        $job('fapshi:reconcile')->everyTwoMinutes();
        $job('store-orders:tick')->everyMinute();
        $job('market-orders:tick')->everyMinute();
        // E-Ride Bus Travel: payments, expiring requests, the timetable opening the coming days.
        $job('bus:tick')->everyMinute();
        $job('hotel:tick')->everyMinute();
        // E-Ride ID Verified: documents past their expiry date stop counting.
        $schedule->call(fn() => app(\Modules\UserManagement\Service\IdVerificationService::class)->expireDue())->name('id-verifications:expire')->dailyAt('01:10')->withoutOverlapping(60);
        $job('trip-deposits:tick')->everyFifteenSeconds();
        // Homes & spaces: the still-available policy, at 8:00 so reminders arrive in the morning.
        $job('listings:sweep')->dailyAt('08:00');
        $job('listings:visit-soon')->everyTenMinutes();
        $job('listing-reservations:tick')->everyMinute();
        // Area pause: planned pauses start and are announced on time; danger reports fade.
        $job('safety:tick')->everyMinute();
        $job('trip-checkins:tick')->everyMinute();
        $job('lost-items:tick')->everyFiveMinutes();
        $job('scheduled-rides:tick')->everyMinute();
        // Voice notes for drivers are kept 30 days.
        $schedule->call(fn() => app(\Modules\TripManagement\Service\TripVoiceNoteService::class)->sweep())->name('trip-voice-notes:sweep')->dailyAt('03:20')->withoutOverlapping(60);
    })
    ->create();

return $app;
