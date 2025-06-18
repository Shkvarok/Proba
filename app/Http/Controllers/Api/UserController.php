<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
        try {
            Log::info('UserController@changeRole: Початок методу', [
                'user_id' => $id,
                'request_data' => $request->all(),
                'auth_user_id' => auth()->id(),
                'auth_user_role' => auth()->user()->role ? auth()->user()->role->name : null
            ]);

            $validator = Validator::make($request->all(), [
                'role_name' => 'required|string|in:student,teacher,admin,super_admin',
            ]);

            if ($validator->fails()) {
                Log::warning('UserController@changeRole: Помилка валідації', [
                    'errors' => $validator->errors()->toArray()
                ]);
                
                return response()->json([
                    'message' => 'Помилка валідації даних',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Знаходимо користувача, якому змінюємо роль
            $user = User::with('role')->findOrFail($id);
            
            // Отримуємо аутентифікованого користувача з роллю
            $authUser = auth()->user();
            if (!$authUser->relationLoaded('role')) {
                $authUser->load('role');
            }

            $authUserRole = $authUser->role ? $authUser->role->name : null;
            $requestedRoleName = $request->role_name;

            Log::info('UserController@changeRole: Дані для перевірки', [
                'target_user_id' => $user->id,
                'target_user_current_role' => $user->role ? $user->role->name : null,
                'auth_user_role' => $authUserRole,
                'requested_role' => $requestedRoleName
            ]);

            // Заборона змінювати роль самому собі
            if ($authUser->id == $user->id) {
                return response()->json([
                    'message' => 'Ви не можете змінити власну роль'
                ], 403);
            }

            // Перевірки дозволів залежно від ролі аутентифікованого користувача
            if ($authUserRole === 'super_admin') {
                // Супер адмін може змінити роль на будь-яку
                Log::info('UserController@changeRole: Супер адмін має повні права');
                
            } elseif ($authUserRole === 'admin') {
                // Адміністратор може змінити роль тільки на teacher або student
                if (in_array($requestedRoleName, ['super_admin', 'admin'])) {
                    Log::warning('UserController@changeRole: Адмін намагається призначити заборонену роль', [
                        'requested_role' => $requestedRoleName
                    ]);
                    
                    return response()->json([
                        'message' => 'Адміністратор може змінювати роль тільки на "Вчитель" або "Студент"',
                        'allowed_roles' => ['teacher', 'student']
                    ], 403);
                }

                // Адміністратор не може змінювати роль супер адміна або іншого адміна
                if ($user->role && in_array($user->role->name, ['super_admin', 'admin'])) {
                    Log::warning('UserController@changeRole: Адмін намагається змінити роль адміна/супер адміна');
                    
                    return response()->json([
                        'message' => 'Адміністратор не може змінювати роль іншого адміністратора або супер адміністратора'
                    ], 403);
                }
                
            } else {
                // Користувачі з іншими ролями не мають права змінювати ролі
                Log::warning('UserController@changeRole: Недостатньо прав', [
                    'user_role' => $authUserRole
                ]);
                
                return response()->json([
                    'message' => 'У вас немає прав для зміни ролей користувачів'
                ], 403);
            }

            // Знаходимо нову роль
            $newRole = Role::where('name', $requestedRoleName)->first();
            
            if (!$newRole) {
                Log::error('UserController@changeRole: Роль не знайдена', [
                    'role_name' => $requestedRoleName
                ]);
                
                return response()->json([
                    'message' => 'Роль не знайдена',
                    'role_name' => $requestedRoleName
                ], 404);
            }

            // Перевіряємо, чи роль дійсно змінилася
            if ($user->role_id == $newRole->id) {
                return response()->json([
                    'message' => 'Користувач вже має цю роль',
                    'current_role' => $user->role->name
                ], 400);
            }

            // Зберігаємо стару роль для логування
            $oldRoleName = $user->role ? $user->role->name : 'немає ролі';

            // Змінюємо роль
            $user->role_id = $newRole->id;
            $user->save();

            Log::info('UserController@changeRole: Роль успішно змінена', [
                'user_id' => $user->id,
                'old_role' => $oldRoleName,
                'new_role' => $newRole->name,
                'changed_by' => $authUser->id
            ]);
            
            return response()->json([
                'message' => 'Роль користувача успішно змінено',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'old_role' => $oldRoleName,
                    'new_role' => $newRole->name
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('UserController@changeRole: Помилка', [
                'user_id' => $id,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Сталася помилка при зміні ролі користувача',
                'error' => $e->getMessage()
            ], 500);
        }
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