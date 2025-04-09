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

        // Якщо ролі не вказані, пропускаємо
        if (empty($roles)) {
            return $next($request);
        }

        // Перевіряємо, чи має користувач хоч одну з необхідних ролей
        foreach ($roles as $role) {
            if ($request->user()->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'У вас немає доступу до цього ресурсу'
        ], 403);
    }
}