<?php
// routes/api/development.php (тільки для розробки)
use Illuminate\Support\Facades\Artisan;

if (app()->environment(['local', 'development'])) {
    Route::prefix('dev')->group(function () {
        Route::get('/test', function() {
            return response()->json(['message' => 'testing'], 200);
        });
        
        Route::get('/test-routes', function() {
            $routes = [];
            foreach (Route::getRoutes() as $route) {
                if (strpos($route->uri, 'api/') !== false) {
                    $routes[] = [
                        'uri' => $route->uri,
                        'methods' => $route->methods,
                        'action' => $route->getActionName()
                    ];
                }
            }
            return response()->json(['routes' => $routes]);
        });
        
        Route::get('/run-seeders', function () {
            Artisan::call('db:seed');
            return response()->json(['message' => 'Seeders have been run successfully.']);
        });
        
        Route::get('/migrate-fresh', function () {
            Artisan::call('migrate:fresh', ['--force' => true]);
            return response()->json(['message' => 'Database has been refreshed.']);
        });
    });
}