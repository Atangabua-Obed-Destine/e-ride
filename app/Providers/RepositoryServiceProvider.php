<?php

namespace App\Providers;


use App\Http\Controllers\BaseController;
use App\Http\Controllers\BaseControllerInterface;
use App\Repository\Eloquent\BaseRepository;
use App\Repository\EloquentRepositoryInterface;
use App\Service\BaseService;
use App\Service\BaseServiceInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //New Controller & Interface
        $this->app->bind(BaseControllerInterface::class, BaseController::class);

        //New Service & Interface
        $this->app->bind(BaseServiceInterface::class, BaseService::class);

        //New Repository & Interface
        $this->app->bind(EloquentRepositoryInterface::class, BaseRepository::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
