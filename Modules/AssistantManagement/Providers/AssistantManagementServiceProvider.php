<?php

namespace Modules\AssistantManagement\Providers;

use Illuminate\Support\ServiceProvider;

class AssistantManagementServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'AssistantManagement';

    protected string $moduleNameLower = 'assistantmanagement';

    public function boot(): void
    {
        $this->mergeConfigFrom(module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower);
        $this->loadViewsFrom(module_path($this->moduleName, 'Resources/views'), $this->moduleNameLower);
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        if (!defined('DOMAIN_POINTED_DIRECTORY')) {
            define('DOMAIN_POINTED_DIRECTORY', 'public');
        }
        $this->app->register(RouteServiceProvider::class);
    }
}
