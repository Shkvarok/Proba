<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Необхідна авторизація'
            ], 401);
        }

        // Якщо дозволи не вказані, пропускаємо
        if (empty($permissions)) {
            return $next($request);
        }

        // Перевіряємо, чи має користувач хоч один з необхідних дозволів
        foreach ($permissions as $permission) {
            if ($request->user()->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'У вас немає доступу до цього ресурсу'
        ], 403);
    }
}