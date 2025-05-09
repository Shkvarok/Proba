<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

use Twilio\Rest\Client;
class PasswordResetController extends Controller
{
    /**
     * Надіслати код для скидання паролю
     */
    public function sendResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'method' => 'required|in:email,sms', // Додано вибір методу
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }

        // Генеруємо 6-значний код
        $code = Str::padLeft(random_int(0, 999999), 6, '0');
        
        // Зберігаємо код в базі даних
        PasswordResetCode::where('email', $request->email)->delete(); // Видаляємо старі коди
        
        $passwordResetCode = PasswordResetCode::create([
            'email' => $request->email,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(30), // Термін дії - 30 хвилин
        ]);

        $user = User::where('email', $request->email)->first();

        try {
            if ($request->method === 'email') {
                // Відправляємо код на електронну пошту
                Mail::raw("Ваш код для скидання паролю: {$code}. Він дійсний протягом 30 хвилин.", function ($message) use ($request) {
                    $message->to($request->email)
                            ->subject('Скидання паролю');
                });
                
                return response()->json([
                    'message' => 'Код для скидання паролю надіслано на вашу електронну пошту'
                ], 200);
            } else if ($request->method === 'sms' && $user->phone_number) {
                // Відправляємо код через SMS
                $this->sendSms($user->phone_number, "Ваш код для скидання паролю: {$code}. Він дійсний протягом 30 хвилин.");
                
                return response()->json([
                    'message' => 'Код для скидання паролю надіслано на ваш телефон'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Не вказано номер телефону для відправки SMS'
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Не вдалося надіслати код для скидання паролю',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Відправити SMS-повідомлення
     */
    private function sendSms(string $phoneNumber, string $message)
    {
        $twilioSid = env('TWILIO_SID');
        $twilioToken = env('TWILIO_TOKEN');
        $twilioNumber = env('TWILIO_NUMBER');
        
        $client = new Client($twilioSid, $twilioToken);
        
        $client->messages->create(
            $phoneNumber,
            [
                'from' => $twilioNumber,
                'body' => $message
            ]
        );
    }
    
    /**
     * Перевірити код скидання паролю
     */
    public function verifyResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }

        $passwordResetCode = PasswordResetCode::where('email', $request->email)
                                             ->where('code', $request->code)
                                             ->first();

        if (!$passwordResetCode) {
            return response()->json([
                'message' => 'Невірний код скидання паролю'
            ], 422);
        }

        if (!$passwordResetCode->isValid()) {
            return response()->json([
                'message' => 'Код скидання паролю прострочений'
            ], 422);
        }

        // Якщо код валідний, повертаємо успішну відповідь
        return response()->json([
            'message' => 'Код скидання паролю дійсний',
            'verified' => true
        ], 200);
    }

    /**
     * Скинути пароль після підтвердження коду
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації даних',
                'errors' => $validator->errors()
            ], 422);
        }

        $passwordResetCode = PasswordResetCode::where('email', $request->email)
                                             ->where('code', $request->code)
                                             ->first();

        if (!$passwordResetCode) {
            return response()->json([
                'message' => 'Невірний код скидання паролю'
            ], 422);
        }

        if (!$passwordResetCode->isValid()) {
            return response()->json([
                'message' => 'Код скидання паролю прострочений'
            ], 422);
        }

        // Оновлюємо пароль користувача
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Видаляємо використаний код
        $passwordResetCode->delete();

        return response()->json([
            'message' => 'Пароль успішно змінено'
        ], 200);
    }
}