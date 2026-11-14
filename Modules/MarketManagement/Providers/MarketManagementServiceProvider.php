<?php

namespace Modules\MarketManagement\Providers;

use Illuminate\Support\ServiceProvider;

class MarketManagementServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'MarketManagement';

    protected string $moduleNameLower = 'marketmanagement';

    public function boot(): void
    {
        $this->mergeConfigFrom(module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower);
        $this->loadViewsFrom(module_path($this->moduleName, 'Resources/views'), $this->moduleNameLower);
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([\Modules\MarketManagement\Console\MarketOrdersTick::class]);
        }
    }

    public function register(): void
    {
        // Web requests define this in index.php; the scheduler and other artisan commands do not, and building an
        // image URL (dynamicStorage) then fails. This server serves public/ (URLs /storage/...).
        if (!defined('DOMAIN_POINTED_DIRECTORY')) {
            define('DOMAIN_POINTED_DIRECTORY', 'public');
        }
        $this->app->register(RouteServiceProvider::class);
    }
}
