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
            'throttle:1000,1',
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
        $schedule->command('trip-request:cancel')->everyMinute()->withoutOverlapping();
        $schedule->command('app:process-scheduled-trips')->everyMinute()->withoutOverlapping();
        $schedule->command('driver:resume-paused')->everyMinute()->withoutOverlapping();
        $schedule->command('driver:sync-availability')->everyMinute()->withoutOverlapping();
        $schedule->command('app:process-late-return-penalty-notifications')->everyMinute()->withoutOverlapping();
    })
    ->create();

return $app;
