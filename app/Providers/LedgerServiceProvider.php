<?php

namespace App\Providers;

use App\Http\Middleware\LedgerBooks;
use App\Http\Middleware\RequireLedgerReview;
use App\Ledger\ERide\Books;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The ledger (App\Ledger: the paxhitestbed accounting module as ported to
 * posinnova) inside E-Ride:
 *  - posinnova's code names its users App\User; here that is E-Ride's user;
 *  - the accounting permissions (paxhitestbed's names) are granted by the bus
 *    roles of the books that are open (App\Ledger\ERide\Books);
 *  - the screens live under /partner/accounting with posinnova's route names
 *    (ledger.*), behind the books check and, for postings, the review.
 */
class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! class_exists('App\User', false)) {
            class_alias(\Modules\UserManagement\Entities\User::class, 'App\User');
        }
    }

    public function boot(): void
    {
        Gate::before(fn ($user, string $ability) => Books::allows($ability));

        // The bus records that post (App\Ledger\LedgerObserver: posinnova's observer, for the bus records).
        \Modules\BusManagement\Entities\BusMoneyMove::observe(\App\Ledger\LedgerObserver::class);
        \Modules\BusManagement\Entities\BusDeparture::observe(\App\Ledger\LedgerObserver::class);
        \Modules\BusManagement\Entities\BusStatement::observe(\App\Ledger\LedgerObserver::class);

        $router = $this->app['router'];
        $router->aliasMiddleware(RequireLedgerReview::ALIAS, RequireLedgerReview::class);

        if (! $this->app->routesAreCached()) {
            Route::middleware(['web', LedgerBooks::class])->prefix('partner')->group(base_path('routes/ledger.php'));
        }
    }
}
