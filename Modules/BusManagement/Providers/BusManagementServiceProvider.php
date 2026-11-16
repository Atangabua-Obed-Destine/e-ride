<?php

namespace Modules\BusManagement\Providers;

use Illuminate\Support\ServiceProvider;

class BusManagementServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'BusManagement';

    protected string $moduleNameLower = 'busmanagement';

    public function boot(): void
    {
        $this->mergeConfigFrom(module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower);
        $this->loadViewsFrom(module_path($this->moduleName, 'Resources/views'), $this->moduleNameLower);
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([\Modules\BusManagement\Console\BusTick::class]);
        }
    }

    public function register(): void
    {
        // Web requests define this in index.php; the scheduler and other artisan commands do not.
        if (!defined('DOMAIN_POINTED_DIRECTORY')) {
            define('DOMAIN_POINTED_DIRECTORY', 'public');
        }
        $this->app->register(RouteServiceProvider::class);
    }
}
