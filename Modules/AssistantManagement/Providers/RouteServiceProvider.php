<?php

namespace Modules\AssistantManagement\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::prefix('api')->middleware('api')->group(module_path('AssistantManagement', '/Routes/api.php'));
        Route::middleware('web')->group(module_path('AssistantManagement', '/Routes/web.php'));
    }
}
