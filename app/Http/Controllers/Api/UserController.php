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
        try {
            \Log::info('UserController@index: Початок методу');
            
            $users = User::with('role')->get();
            
            \Log::info('UserController@index: Користувачів завантажено', [
                'count' => count($users)
            ]);
            
            return response()->json([
                'users' => $users
            ], 200);
        } catch (\Exception $e) {
            \Log::error('UserController@index: Помилка', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Сталася помилка при отриманні користувачів',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримання списку адміністраторів
     */
    public function admins()
    {
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
        try {
            \Log::info('UserController@storeAdmin: Початок виконання', [
                'request_data' => $request->all()
            ]);
            
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50',
                'country_id' => 'nullable|exists:countries,id',
                'phone_number' => 'nullable|string|max:20',
                'is_super_admin' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                \Log::warning('UserController@storeAdmin: Помилка валідації', [
                    'errors' => $validator->errors()->toArray()
                ]);
                
                return response()->json([
                    'message' => 'Помилка валідації даних',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Визначаємо роль для нового користувача
            $roleName = 'admin';
            
            // Перевіряємо, чи аутентифікований користувач
            if (auth()->check()) {
                // Явно завантажуємо роль, якщо вона ще не завантажена
                $authUser = auth()->user();
                if (!$authUser->relationLoaded('role')) {
                    $authUser->load('role');
                }
                
                \Log::info('UserController@storeAdmin: Перевірка ролі аутентифікованого користувача', [
                    'user_id' => $authUser->id,
                    'role_id' => $authUser->role_id,
                    'role' => $authUser->role ? $authUser->role->name : 'null'
                ]);
                
                // Безпечна перевірка на super_admin
                if ($authUser->role && $authUser->role->name === 'super_admin' && 
                    $request->has('is_super_admin') && $request->is_super_admin) {
                    $roleName = 'super_admin';
                    \Log::info('UserController@storeAdmin: Створення super_admin');
                }
            } else {
                \Log::warning('UserController@storeAdmin: Користувач не аутентифікований');
                return response()->json([
                    'message' => 'Необхідна авторизація'
                ], 401);
            }
            
            // Знаходимо роль
            $role = Role::where('name', $roleName)->first();
            
            \Log::info('UserController@storeAdmin: Пошук ролі', [
                'role_name' => $roleName,
                'role_found' => $role ? 'yes' : 'no'
            ]);
            
            if (!$role) {
                return response()->json([
                    'message' => 'Не знайдено потрібну роль'
                ], 500);
            }

            // Створюємо користувача
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'name' => $request->name,
                'last_name' => $request->last_name,
                'country_id' => $request->country_id ?? null,
                'phone_number' => $request->phone_number,
                'role_id' => $role->id,
                'email_verified_at' => now(),
            ]);
            
            \Log::info('UserController@storeAdmin: Користувача створено', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'message' => 'Адміністратора успішно створено',
                'admin' => $user->load('role')
            ], 201);
        } catch (\Exception $e) {
            \Log::error('UserController@storeAdmin: Помилка', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Сталася помилка при створенні адміністратора',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Створення вчителя
     */
    public function storeTeacher(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'name' => 'required|string|max:50',
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

        // Отримуємо роль вчителя
        $teacherRole = Role::where('name', 'teacher')->first();
        if (!$teacherRole) {
            return response()->json([
                'message' => 'Не знайдено роль вчителя'
            ], 500);
        }

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'name' => $request->name,
            'last_name' => $request->last_name,
            'country_id' => $request->country_id,
            'phone_number' => $request->phone_number,
            'role_id' => $teacherRole->id,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Вчителя успішно створено',
            'teacher' => $user->load('role')
        ], 201);
    }

    /**
     * Отримання інформації про користувача
     */
    public function show($id)
    {
        try {
            \Log::info('UserController@show: Початок методу', ['id' => $id]);
            
            $user = User::with('role', 'country')->findOrFail($id);
            
            \Log::info('UserController@show: Користувача знайдено', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return response()->json([
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            \Log::error('UserController@show: Помилка', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Сталася помилка при отриманні користувача',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Оновлення інформації про користувача
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'name' => 'sometimes|required|string|max:50',
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
            $newRole = Role::find($request->role_id);
            
            // Якщо намагаються встановити роль супер-адміна
            if ($newRole && $newRole->name == 'super_admin') {
                // Тільки супер-адмін може призначати роль супер-адміна
                if (auth()->user()->role->name !== 'super_admin') {
                    return response()->json([
                        'message' => 'У вас немає дозволу призначати роль Super Admin'
                    ], 403);
                }
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
     * Зміна ролі користувача
     */
    public function changeRole(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $newRole = Role::findOrFail($request->role_id);
        
        // Перевірка на спробу зміни ролі на super_admin
        if ($newRole->name === 'super_admin' && auth()->user()->role->name !== 'super_admin') {
            return response()->json([
                'message' => 'Тільки супер-адміністратор може призначати роль супер-адміністратора'
            ], 403);
        }

        $user->role_id = $request->role_id;
        $user->save();
        
        return response()->json([
            'message' => 'Роль користувача успішно змінено',
            'user' => $user->load('role')
        ], 200);
    }

    /**
     * Видалення користувача
     */
   public function destroy($id)
{
    $user = User::findOrFail($id);

    // Заборона видаляти себе
    if (auth()->id() == $id) {
        return response()->json([
            'message' => 'Ви не можете видалити власний обліковий запис'
        ], 403);
    }

    if ($user->role && $user->role->name === 'super_admin') {
        return response()->json([
            'message' => 'Користувача з роллю Super Admin не можна видалити'
        ], 403);
    }

    // Заборона видаляти користувача з ID = 1
    // if ($id == 1) {
    //     return response()->json([
    //         'message' => 'Super Admin не може бути видалений'
    //     ], 403);
    // }


    // Звичайний адмін не може видаляти Super Admin
    if ($user->role && $user->role->name == 'super_admin' && auth()->user()->role->name != 'super_admin') {
        return response()->json([
            'message' => 'У вас немає дозволу видаляти Super Admin'
        ], 403);
    }

    $user->delete();

    return response()->json([
        'message' => 'Користувача успішно видалено'
    ], 200);
}

    public function __construct()
{
    \Log::info('UserController: Конструктор викликано', [
        'auth' => auth()->check() ? 'authenticated' : 'not authenticated',
        'user_id' => auth()->check() ? auth()->id() : null,
        'role' => auth()->check() && auth()->user()->role ? auth()->user()->role->name : null
    ]);
}
}