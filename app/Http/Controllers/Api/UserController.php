<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Отримання списку всіх користувачів
     */
    public function index()
    {
        if (!auth()->user()) {
            return response()->json([
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        // Перевіряємо чи має користувач дозвіл на перегляд користувачів
        if (!auth()->user()->hasPermission('view-users')) {
            return response()->json([
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }

        $users = User::with('role')->get();

        return response()->json([
            'users' => $users
        ], 200);
    }

    /**
     * Отримання списку адміністраторів
     */
    public function admins()
    {
        if (!auth()->user()) {
            return response()->json([
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        
        if (!auth()->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }
        
        // Отримуємо ID ролей для адміністраторів
        $adminRoles = Role::whereIn('name', ['admin', 'super_admin'])->pluck('id');
        
        $admins = User::whereIn('role_id', $adminRoles)->with('role')->get();
        
        return response()->json([
            'admins' => $admins
        ], 200);
    }

    /**
     * Створення адміністратора
     */
    public function storeAdmin(Request $request)
    {
        if (!auth()->user()) {
            return response()->json([
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        // Перевіряємо чи користувач має право на створення користувачів
        if (!auth()->user()->hasPermission('create-users')) {
            return response()->json([
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'country_id' => 'nullable|exists:countries,id',
            'phone_number' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }

        // Отримуємо роль адміністратора
        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            return response()->json([
                'message' => 'Не знайдено роль адміністратора'
            ], 500);
        }

        // Якщо користувач є супер-адміном і хоче створити супер-адміна
        if (auth()->user()->hasRole('super_admin') && $request->has('is_super_admin') && $request->is_super_admin) {
            $adminRole = Role::where('name', 'super_admin')->first();
        }

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'country_id' => $request->country_id,
            'phone_number' => $request->phone_number,
            'role_id' => $adminRole->id,
            'email_verified_at' => now(), // Верифікуємо email одразу
        ]);

        return response()->json([
            'message' => 'Адміністратора успішно створено',
            'admin' => $user->load('role')
        ], 201);
    }

    /**
     * Отримання інформації про користувача
     */
    public function show($id)
    {
        if (!auth()->user()) {
            return response()->json([
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        // Перевіряємо чи має користувач дозвіл на перегляд користувачів
        if (!auth()->user()->hasPermission('view-users')) {
            return response()->json([
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }

        $user = User::with('role', 'country')->findOrFail($id);

        return response()->json([
            'user' => $user
        ], 200);
    }

    /**
     * Оновлення інформації про користувача
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()) {
            return response()->json([
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        // Перевіряємо чи має користувач дозвіл на редагування користувачів
        if (!auth()->user()->hasPermission('edit-users')) {
            // Дозволяємо користувачам редагувати власний профіль
            if (auth()->id() != $id) {
                return response()->json([
                    'message' => 'У вас немає доступу до цього ресурсу'
                ], 403);
            }
        }

        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'first_name' => 'sometimes|required|string|max:50',
            'last_name' => 'sometimes|required|string|max:50',
            'country_id' => 'nullable|exists:countries,id',
            'phone_number' => 'nullable|string|max:20',
            'role_id' => 'sometimes|required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }

        // Перевіряємо, чи може поточний користувач змінювати роль
        if ($request->has('role_id') && $user->role_id != $request->role_id) {
            // Тільки адміни можуть змінювати ролі
            if (!auth()->user()->isAdmin()) {
                return response()->json([
                    'message' => 'У вас немає дозволу змінювати роль користувача'
                ], 403);
            }
            
            // Звичайний адмін не може призначати роль Super Admin
            $newRole = Role::find($request->role_id);
            if ($newRole && $newRole->name == 'super_admin' && !auth()->user()->hasRole('super_admin')) {
                return response()->json([
                    'message' => 'У вас немає дозволу призначати роль Super Admin'
                ], 403);
            }
        }

        // Оновлюємо користувача
        $user->update($request->all());

        return response()->json([
            'message' => 'Інформацію про користувача успішно оновлено',
            'user' => $user->fresh()->load('role', 'country')
        ], 200);
    }

    /**
     * Видалення користувача
     */
    public function destroy($id)
    {
        if (!auth()->user()) {
            return response()->json([
                'message' => 'Необхідна авторизація'
            ], 401);
        }
        // Перевіряємо чи має користувач дозвіл на видалення користувачів
        if (!auth()->user()->hasPermission('delete-users')) {
            return response()->json([
                'message' => 'У вас немає доступу до цього ресурсу'
            ], 403);
        }

        $user = User::findOrFail($id);

        // Заборона видаляти себе
        if (auth()->id() == $id) {
            return response()->json([
                'message' => 'Ви не можете видалити власний обліковий запис'
            ], 403);
        }

        // Звичайний адмін не може видаляти Super Admin
        if ($user->hasRole('super_admin') && !auth()->user()->hasRole('super_admin')) {
            return response()->json([
                'message' => 'У вас немає дозволу видаляти Super Admin'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'Користувача успішно видалено'
        ], 200);
    }
}