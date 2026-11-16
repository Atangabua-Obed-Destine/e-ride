<?php

namespace Modules\BusManagement\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::prefix('api')->middleware('api')->group(module_path('BusManagement', '/Routes/api.php'));
        Route::middleware('web')->group(module_path('BusManagement', '/Routes/web.php'));
    }
}
