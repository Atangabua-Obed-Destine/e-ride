<?php

namespace Modules\HotelManagement\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::prefix('api')->middleware('api')->group(module_path('HotelManagement', '/Routes/api.php'));
        Route::middleware('web')->group(module_path('HotelManagement', '/Routes/web.php'));
    }
}
