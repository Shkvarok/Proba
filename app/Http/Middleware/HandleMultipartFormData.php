<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleMultipartFormData
{
    public function handle(Request $request, Closure $next)
    {
        // Перевіряємо чи це PUT/PATCH запит з multipart/form-data
        if (in_array($request->method(), ['PUT', 'PATCH']) && 
            $request->hasHeader('Content-Type') && 
            str_contains($request->header('Content-Type'), 'multipart/form-data')) {
            
            // Laravel автоматично не обробляє _method для файлових запитів
            if ($request->has('_method')) {
                $request->setMethod($request->input('_method'));
            }
        }

        return $next($request);
    }
}