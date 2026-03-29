<?php

namespace App\Providers;

use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Eloquent\EloquentProjectRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProjectRepositoryInterface::class,
            EloquentProjectRepository::class,
        );
    }

    public function boot(): void
    {
        //
    }
}
