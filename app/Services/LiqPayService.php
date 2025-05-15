<?php

namespace App\Services;

use LiqPay;
use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use App\Models\CourseEnrollment;
use App\Notifications\CoursePaymentSuccessful;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class LiqPayService
{
    protected $liqpay;
    protected $publicKey;
    protected $privateKey;
    protected $sandbox;

    public function __construct()
    {
        $this->publicKey = config('liqpay.public_key');
        $this->privateKey = config('liqpay.private_key');
        $this->sandbox = config('liqpay.sandbox');
        $this->liqpay = new LiqPay($this->publicKey, $this->privateKey);
    }

    /**
     * Створює форму оплати для курсу
     * 
     * @param User|Collection $user
     * @param Course|Collection $course
     * @param Payment|Collection $payment
     * @return array Дані для формування форми оплати
     */
    public function createCoursePaymentForm($user, $course, $payment)
    {
        // Перевірка, що параметри мають правильний тип
        if ($user instanceof Collection) {
            $user = $user->first();
        }
        
        if ($course instanceof Collection) {
            $course = $course->first();
        }
        
        if ($payment instanceof Collection) {
            $payment = $payment->first();
        }
        
        $callbackUrl = route('liqpay.callback');
        $returnUrl = route('courses.payment.success', ['courseId' => $course->id]);
        
        $description = "Оплата за курс: {$course->title}";
        
        $params = [
            'action'         => 'pay',
            'amount'         => $course->price,
            'currency'       => 'UAH',
            'description'    => $description,
            'order_id'       => $payment->id . '_' . Str::random(8),
            'version'        => '3',
            'language'       => 'uk',
            'sandbox'        => $this->sandbox ? '1' : '0',
            'server_url'     => $callbackUrl, // URL для серверного сповіщення
            'result_url'     => $returnUrl,   // URL для перенаправлення користувача
            'email'          => $user->email,
            
            // Додаткові дані для ідентифікації платежу
            'info'           => json_encode([
                'payment_id' => $payment->id,
                'user_id'    => $user->id,
                'course_id'  => $course->id,
            ])
        ];
        
        // Підготовка даних для форми оплати
        $data = $this->liqpay->cnb_form($params);
        
        return [
            'url' => 'https://www.liqpay.ua/api/3/checkout',
            'data' => $data,
            'params' => $params
        ];
    }

    /**
     * Обробка результату оплати від LiqPay
     * 
     * @param array $data Дані від LiqPay
     * @return array Оброблені дані платежу
     */
    public function processCallback($data)
    {
        // Перевірка підпису для запобігання підробки запиту
        $sign = base64_encode(sha1($this->privateKey . $data['data'] . $this->privateKey, 1));
        
        if ($sign !== $data['signature']) {
            return [
                'status' => 'error',
                'message' => 'Invalid signature'
            ];
        }

        // Декодування даних
        $decodedData = json_decode(base64_decode($data['data']), true);
        
        // Отримуємо додаткову інформацію, яку передали при створенні платежу
        $paymentInfo = json_decode($decodedData['info'] ?? '{}', true);
        
        // Знаходимо платіж в нашій системі
        $payment = Payment::find($paymentInfo['payment_id'] ?? null);
        
        if (!$payment) {
            return [
                'status' => 'error',
                'message' => 'Payment not found'
            ];
        }
        
        // Оновлюємо дані платежу
        $payment->transaction_id = $decodedData['transaction_id'] ?? null;
        $payment->payment_status = $this->mapLiqPayStatus($decodedData['status'] ?? '');
        $payment->save();
        
        // Якщо платіж успішний - створюємо підписку
        if ($payment->payment_status === 'completed') {
            $enrollment = $this->createEnrollment($payment);
            
            return [
                'status' => 'success',
                'payment' => $payment,
                'enrollment' => $enrollment
            ];
        }
        
        return [
            'status' => 'pending',
            'payment' => $payment
        ];
    }
    
    /**
     * Мапінг статусів LiqPay на статуси нашої системи
     * 
     * @param string $liqpayStatus
     * @return string
     */
    protected function mapLiqPayStatus($liqpayStatus)
    {
        $statusMap = [
            'success' => 'completed',
            'wait_accept' => 'pending',
            'failure' => 'failed',
            'reversed' => 'refunded',
            'sandbox' => 'completed', // у тестовому режимі всі успішні платежі мають статус sandbox
        ];
        
        return $statusMap[$liqpayStatus] ?? 'pending';
    }
    
    /**
     * Створює підписку на курс після успішної оплати
     * 
     * @param Payment|Collection $payment
     * @return CourseEnrollment|null
     */
    protected function createEnrollment($payment)
    {
        // Перевірка, що параметр має правильний тип
        if ($payment instanceof Collection) {
            $payment = $payment->first();
        }
        
        // Перевіряємо, чи це оплата за курс
        if ($payment->entity_type !== 'course') {
            Log::info('Payment is not for a course', ['payment_id' => $payment->id, 'entity_type' => $payment->entity_type]);
            return null;
        }
        
        // Перевіряємо, чи вже існує підписка
        $existingEnrollment = CourseEnrollment::where('payment_id', $payment->id)->first();
        
        if ($existingEnrollment) {
            Log::info('Enrollment already exists', ['payment_id' => $payment->id, 'enrollment_id' => $existingEnrollment->id]);
            return $existingEnrollment;
        }
        
        try {
            // Створюємо нову підписку
            $enrollment = CourseEnrollment::create([
                'user_id' => $payment->user_id,
                'course_id' => $payment->entity_id,
                'enrollment_type' => 'purchase',
                'payment_id' => $payment->id,
                'is_active' => true,
            ]);
            
            // Відправка нотифікації
            $user = User::find($payment->user_id);
            $course = Course::find($payment->entity_id);
            
            if ($user && $course) {
                // Переконуємося, що $course є об'єктом Course
                if ($course instanceof Collection) {
                    $course = $course->first();
                }
                
                $user->notify(new CoursePaymentSuccessful($payment, $course));
                Log::info('Course payment notification sent', [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'payment_id' => $payment->id
                ]);
            }
            
            return $enrollment;
        } catch (\Exception $e) {
            Log::error('Error creating enrollment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Повертаємо null у випадку помилки
            return null;
        }
    }
}