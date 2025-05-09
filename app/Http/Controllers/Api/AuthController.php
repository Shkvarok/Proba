<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Реєстрація нового користувача
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
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
        
        // За замовчуванням реєструємо студентів
        $studentRole = Role::where('name', 'student')->first();
        if (!$studentRole) {
            return response()->json([
                'message' => 'Не знайдено роль студента'
            ], 500);
        }
        
        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'name' => $request->name,
            'last_name' => $request->last_name,
            'country_id' => $request->country_id,
            'phone_number' => $request->phone_number,
            'role_id' => $studentRole->id,
        ]);
        
        $token = $user->createToken('auth-token')->plainTextToken;
        
        return response()->json([
            'message' => 'Користувача успішно зареєстровано',
            'user' => $user,
            'token' => $token
        ], 201);
    }
    
    /**
     * Вхід користувача
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }
        
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            throw ValidationException::withMessages([
                'email' => ['Надані облікові дані невірні.'],
            ]);
        }
        
        $user = User::where('email', $request->email)->first();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        return response()->json([
            'message' => 'Успішний вхід',
            'user' => $user->load('role'),
            'token' => $token
        ], 200);
    }
    
    /**
     * Вихід користувача (видалення токена)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Успішний вихід з системи'
        ], 200);
    }
    
    /**
     * Отримання інформації про поточного користувача
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('role', 'country');
        
        return response()->json([
            'user' => $user
        ], 200);
    }
}