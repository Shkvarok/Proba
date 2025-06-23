<?php

namespace App\Providers;

use App\Repositories\CategoryRepository;
use App\Repositories\LevelRepository;
use App\Services\CategoryService;
use App\Services\LevelService;
use Illuminate\Support\ServiceProvider;
use App\Services\ProfileService; // Додано ProfileService

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

                // Реєстрація ProfileService
                $this->app->singleton(ProfileService::class, function ($app) {
                    return new ProfileService();
                });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
         // Налаштування JSON для всього додатку
    if (app()->environment('local', 'development')) {
        \Illuminate\Http\Resources\Json\JsonResource::withoutWrapping();
        
        // Налаштування JSON форматування
        app()->bind('json.flags', function() {
            return JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        });
    }
    }
}