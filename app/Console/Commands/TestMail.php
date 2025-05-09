<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'mail:test {email?}';
    protected $description = 'Тестування відправки пошти';

    public function handle()
    {
        $email = $this->argument('email') ?: 'test@example.com';
        
        $this->info("Відправлення тестового листа на {$email}");
        
        try {
            Mail::raw('Це тестовий лист від Laravel', function ($message) use ($email) {
                $message->to($email)
                        ->subject('Тестування відправки пошти');
            });
            
            $this->info('Лист успішно відправлено! Перевірте Mailpit.');
        } catch (\Exception $e) {
            $this->error('Помилка при відправленні: ' . $e->getMessage());
        }
        
        return 0;
    }
}