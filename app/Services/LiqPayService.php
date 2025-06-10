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
        
        // Перевірка наявності ключів
        if (empty($this->publicKey) || empty($this->privateKey)) {
            Log::error('LiqPay keys are not configured properly');
            throw new \Exception('LiqPay configuration is missing');
        }
        
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
        try {
            // Перевірка, що параметри мають правильний тип
            if ($user instanceof Collection) {
                $user = $user->first();
                if (!$user) {
                    throw new \Exception('User not found in collection');
                }
            }
            
            if ($course instanceof Collection) {
                $course = $course->first();
                if (!$course) {
                    throw new \Exception('Course not found in collection');
                }
            }
            
            if ($payment instanceof Collection) {
                $payment = $payment->first();
                if (!$payment) {
                    throw new \Exception('Payment not found in collection');
                }
            }
            
            // Валідація об'єктів
            if (!$user instanceof User || !$course instanceof Course || !$payment instanceof Payment) {
                throw new \Exception('Invalid object types provided to createCoursePaymentForm');
            }
            
            $callbackUrl = route('liqpay.callback');
            $returnUrl = route('courses.payment.success', ['courseId' => $course->id]);
            
            $description = "Оплата за курс: {$course->title}";
            
            $params = [
                'action'         => 'pay',
                'amount'         => (string) $course->price,
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
            
            Log::info('Creating LiqPay form', [
                'payment_id' => $payment->id,
                'course_id' => $course->id,
                'user_id' => $user->id,
                'amount' => $course->price
            ]);
            
            // Підготовка даних для форми оплати
            $data = $this->liqpay->cnb_form($params);
            
            return [
                'url' => 'https://www.liqpay.ua/api/3/checkout',
                'data' => $data,
                'params' => $params
            ];
            
        } catch (\Exception $e) {
            Log::error('Error creating LiqPay form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Обробка результату оплати від LiqPay
     * 
     * @param array $data Дані від LiqPay
     * @return array Оброблені дані платежу
     */
    public function processCallback($data)
    {
        try {
            // Перевірка наявності необхідних ключів
            if (!isset($data['data']) || !isset($data['signature'])) {
                Log::error('LiqPay callback is missing required fields', [
                    'received_data' => $data
                ]);
                return [
                    'status' => 'error',
                    'message' => 'Invalid callback data structure'
                ];
            }

            // Перевірка підпису для запобігання підробки запиту
            $sign = base64_encode(sha1($this->privateKey . $data['data'] . $this->privateKey, 1));
            
            if ($sign !== $data['signature']) {
                Log::error('LiqPay callback signature verification failed', [
                    'expected' => $sign,
                    'received' => $data['signature']
                ]);
                return [
                    'status' => 'error',
                    'message' => 'Invalid signature'
                ];
            }

            // Декодування даних
            $decodedData = json_decode(base64_decode($data['data']), true);
            
            if (!$decodedData) {
                Log::error('Failed to decode LiqPay data', [
                    'data' => $data['data']
                ]);
                return [
                    'status' => 'error',
                    'message' => 'Failed to decode callback data'
                ];
            }
            
            // Отримуємо додаткову інформацію, яку передали при створенні платежу
            $paymentInfo = json_decode($decodedData['info'] ?? '{}', true);
            
            // Знаходимо платіж в нашій системі
            $payment = Payment::find($paymentInfo['payment_id'] ?? null);
            
            if (!$payment) {
                Log::error('Payment not found in callback', [
                    'payment_info' => $paymentInfo,
                    'decoded_data' => $decodedData
                ]);
                return [
                    'status' => 'error',
                    'message' => 'Payment not found'
                ];
            }
            
            Log::info('Processing LiqPay callback', [
                'payment_id' => $payment->id,
                'liqpay_status' => $decodedData['status'] ?? 'unknown',
                'transaction_id' => $decodedData['transaction_id'] ?? null
            ]);
            
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
            
        } catch (\Exception $e) {
            Log::error('Error processing LiqPay callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'callback_data' => $data
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Processing error: ' . $e->getMessage()
            ];
        }
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
            'processing' => 'pending',
            'prepared' => 'pending',
            'wait_secure' => 'pending',
        ];
        
        $mappedStatus = $statusMap[$liqpayStatus] ?? 'pending';
        
        Log::info('Mapped LiqPay status', [
            'original' => $liqpayStatus,
            'mapped' => $mappedStatus
        ]);
        
        return $mappedStatus;
    }
    
    /**
     * Створює підписку на курс після успішної оплати
     * 
     * @param Payment|Collection $payment
     * @return CourseEnrollment|null
     */
    protected function createEnrollment($payment)
    {
        try {
            // Перевірка, що параметр має правильний тип
            if ($payment instanceof Collection) {
                $payment = $payment->first();
                if (!$payment) {
                    throw new \Exception('Payment not found in collection');
                }
            }
            
            if (!$payment instanceof Payment) {
                throw new \Exception('Invalid payment object');
            }
            
            // Перевіряємо, чи це оплата за курс
            if ($payment->entity_type !== 'course') {
                Log::info('Payment is not for a course', [
                    'payment_id' => $payment->id, 
                    'entity_type' => $payment->entity_type
                ]);
                return null;
            }
            
            // Перевіряємо, чи вже існує підписка
            $existingEnrollment = CourseEnrollment::where('payment_id', $payment->id)->first();
            
            if ($existingEnrollment) {
                Log::info('Enrollment already exists', [
                    'payment_id' => $payment->id, 
                    'enrollment_id' => $existingEnrollment->id
                ]);
                return $existingEnrollment;
            }
            
            // Перевіряємо існування курсу та користувача
            $course = Course::find($payment->entity_id);
            $user = User::find($payment->user_id);
            
            if (!$course) {
                Log::error('Course not found for payment', [
                    'payment_id' => $payment->id,
                    'course_id' => $payment->entity_id
                ]);
                return null;
            }
            
            if (!$user) {
                Log::error('User not found for payment', [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id
                ]);
                return null;
            }
            
            // Створюємо нову підписку
            $enrollment = CourseEnrollment::create([
                'user_id' => $payment->user_id,
                'course_id' => $payment->entity_id,
                'enrollment_type' => 'purchase',
                'payment_id' => $payment->id,
                'is_active' => true,
            ]);
            
            Log::info('Course enrollment created successfully', [
                'payment_id' => $payment->id,
                'enrollment_id' => $enrollment->id,
                'user_id' => $payment->user_id,
                'course_id' => $payment->entity_id
            ]);
            
            // Відправка нотифікації
            try {
                // Переконуємося, що $course є об'єктом Course
                if ($course instanceof Collection) {
                    $course = $course->first();
                }
                
                if ($course && $user) {
                    $user->notify(new CoursePaymentSuccessful($payment, $course));
                    Log::info('Course payment notification sent', [
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'payment_id' => $payment->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send payment notification', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
                // Не кидаємо помилку, щоб не перервати створення підписки
            }
            
            return $enrollment;
            
        } catch (\Exception $e) {
            Log::error('Error creating enrollment', [
                'payment_id' => $payment->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Повертаємо null у випадку помилки
            return null;
        }
    }
    
    /**
     * Перевірка статусу платежу в LiqPay
     * 
     * @param string $orderId
     * @return array|null
     */
    public function checkPaymentStatus($orderId)
    {
        try {
            $params = [
                'action' => 'status',
                'version' => '3',
                'order_id' => $orderId
            ];
            
            $result = $this->liqpay->api('request', $params);
            
            Log::info('LiqPay status check result', [
                'order_id' => $orderId,
                'result' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Error checking LiqPay payment status', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}