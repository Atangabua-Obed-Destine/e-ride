<?php

namespace Modules\StoreManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

class StoreManagementServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'StoreManagement';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'storemanagement';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([\Modules\StoreManagement\Console\StoreOrdersTick::class]);
        }

        // A food order follows its delivery trip. Synced after the trip's own
        // transaction commits, so a rolled-back status change never moves it.
        \Modules\TripManagement\Entities\TripRequest::updated(function ($trip) {
            if (!$trip->wasChanged('current_status') || $trip->type !== PARCEL) {
                return;
            }
            $tripId = $trip->id;
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($tripId) {
                $order = \Modules\StoreManagement\Entities\StoreOrder::where('trip_request_id', $tripId)->first();
                if ($order) {
                    try {
                        app(\Modules\StoreManagement\Service\Interfaces\StoreOrderServiceInterface::class)->syncFromTrip($order);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('Food & Shops trip sync failed', ['order' => $order->id, 'error' => $e->getMessage()]);
                    }
                }
            });
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        require_once module_path($this->moduleName, 'Lib/helpers.php');
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }
}
