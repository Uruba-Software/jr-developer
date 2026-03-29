<?php

namespace App\Providers;

use App\Adapters\SlackAdapter;
use App\Contracts\MessagingPlatform;
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

        $this->app->bind(
            MessagingPlatform::class,
            SlackAdapter::class,
        );
    }

    public function boot(): void
    {
        //
    }
}
