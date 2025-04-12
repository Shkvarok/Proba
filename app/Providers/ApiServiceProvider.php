<?php

namespace App\Providers;

use App\Repositories\CategoryRepository;
use App\Repositories\LevelRepository;
use App\Services\CategoryService;
use App\Services\LevelService;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Реєстрація репозиторіїв
        $this->app->singleton(CategoryRepository::class, function ($app) {
            return new CategoryRepository();
        });

        $this->app->singleton(LevelRepository::class, function ($app) {
            return new LevelRepository();
        });

        // Реєстрація сервісів
        $this->app->singleton(CategoryService::class, function ($app) {
            return new CategoryService(
                $app->make(CategoryRepository::class)
            );
        });

        $this->app->singleton(LevelService::class, function ($app) {
            return new LevelService(
                $app->make(LevelRepository::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}