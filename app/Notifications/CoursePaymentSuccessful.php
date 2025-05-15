<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class CoursePaymentSuccessful extends Notification implements ShouldQueue
{
    use Queueable;

    protected $payment;
    protected $course;

    /**
     * Create a new notification instance.
     * 
     * @param Payment|Collection $payment
     * @param Course|Collection $course
     */
    public function __construct($payment, $course)
    {
        // Забезпечуємо, що параметри мають правильний тип
        if ($payment instanceof Collection) {
            $this->payment = $payment->first();
        } else {
            $this->payment = $payment;
        }
        
        if ($course instanceof Collection) {
            $this->course = $course->first();
        } else {
            $this->course = $course;
        }
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Доступ до курсу відкрито!')
            ->greeting('Вітаємо!')
            ->line('Ваш платіж успішно оброблено.')
            ->line("Ви отримали доступ до курсу \"{$this->course->title}\".")
            ->action('Почати навчання', url("/courses/{$this->course->id}"))
            ->line('Дякуємо за вибір нашої платформи!');
    }
}