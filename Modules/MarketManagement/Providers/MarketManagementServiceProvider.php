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
        $this->app->register(RouteServiceProvider::class);
    }
}
