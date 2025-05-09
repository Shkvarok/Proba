<?php

namespace App\Console\Commands;

use App\Models\PasswordResetCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TestPasswordReset extends Command
{
    protected $signature = 'password:reset-test {email}';
    protected $description = 'Тестування системи скидання паролю';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Створення коду скидання паролю для {$email}");
        
        // Генеруємо 6-значний код
        $code = Str::padLeft(random_int(0, 999999), 6, '0');
        
        // Зберігаємо код в базі даних
        PasswordResetCode::where('email', $email)->delete(); // Видаляємо старі коди
        
        $passwordResetCode = PasswordResetCode::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(30), // Термін дії - 30 хвилин
        ]);
        
        $this->info("Створено код: {$code}");
        
        // Відправляємо код електронною поштою
        try {
            Mail::raw("Ваш код для скидання паролю: {$code}. Він дійсний протягом 30 хвилин.", function ($message) use ($email) {
                $message->to($email)
                        ->subject('Скидання паролю');
            });
            
            $this->info('Лист з кодом успішно відправлено! Перевірте Mailpit.');
        } catch (\Exception $e) {
            $this->error('Помилка при відправленні: ' . $e->getMessage());
        }
        
        return 0;
    }
}