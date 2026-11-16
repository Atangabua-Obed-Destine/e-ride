<?php

namespace Modules\HotelManagement\Providers;

use Illuminate\Support\ServiceProvider;

class HotelManagementServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'HotelManagement';

    protected string $moduleNameLower = 'hotelmanagement';

    public function boot(): void
    {
        $this->mergeConfigFrom(module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower);
        $this->loadViewsFrom(module_path($this->moduleName, 'Resources/views'), $this->moduleNameLower);
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([\Modules\HotelManagement\Console\HotelTick::class]);
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
