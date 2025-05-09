<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        Log::info('CheckRole middleware: Start', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'roles' => $roles
        ]);
        
        if (!$request->user()) {
            Log::error('CheckRole middleware: No authenticated user');
            return response()->json([
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        
        $user = $request->user();
        
        // Явно завантажуємо відношення ролі, якщо воно не завантажено
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        Log::info('CheckRole middleware: User', [
            'id' => $user->id,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'role' => $user->role ? $user->role->name : 'null'
        ]);
        
        $userRole = $user->role ? $user->role->name : null;
        
        // Якщо користувач є super_admin, автоматично надаємо доступ до будь-якого ресурсу
        if ($userRole === 'super_admin') {
            Log::info('CheckRole middleware: Access granted (super_admin override)');
            return $next($request);
        }
        
        // Для інших ролей виконуємо звичайну перевірку
        if (!$userRole || !in_array($userRole, $roles)) {
            Log::error('CheckRole middleware: Access denied', [
                'user_role' => $userRole,
                'required_roles' => $roles
            ]);
            
            return response()->json([
                'message' => 'У вас немає доступу до цього ресурсу',
                'required_roles' => $roles,
                'your_role' => $userRole
            ], 403);
        }
        
        Log::info('CheckRole middleware: Access granted');
        return $next($request);
    }
}