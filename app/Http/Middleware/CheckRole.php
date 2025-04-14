<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Необхідна авторизація'
            ], 401);
        }

        // Тимчасово пропускаємо користувача з роллю super_admin без додаткових перевірок
        if (isset($request->user()->role->name) && $request->user()->role->name === 'super_admin') {
            return $next($request);
        }

        // Якщо ролі не вказані, пропускаємо
        if (empty($roles)) {
            return $next($request);
        }
    
        // Перевірка ролі, яка є об'єктом
        if (isset($request->user()->role->name) && in_array($request->user()->role->name, $roles)) {
            return $next($request);
        }
    
        return response()->json([
            'message' => 'У вас немає доступу до цього ресурсу'
        ], 403);
    }
}