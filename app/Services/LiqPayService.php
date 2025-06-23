<?php

namespace App\Services;

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
    protected $publicKey;
    protected $privateKey;
    protected $sandbox;

    public function __construct()
    {
        $this->publicKey = config('liqpay.public_key');
        $this->privateKey = config('liqpay.private_key');
        $this->sandbox = config('liqpay.sandbox', true);
        
        // Перевірка наявності ключів
        if (empty($this->publicKey) || empty($this->privateKey)) {
            Log::error('LiqPay keys are not configured properly');
            throw new \Exception('LiqPay configuration is missing');
        }
    }

    /**
     * Генерує підпис для LiqPay
     */
    private function generateSignature($data)
    {
        return base64_encode(sha1($this->privateKey . $data . $this->privateKey, true));
    }

    /**
     * Генерує HTML форму для оплати
     */
    private function generateFormHtml($data, $signature)
    {
        return sprintf(
            '<form method="POST" action="https://www.liqpay.ua/api/3/checkout" accept-charset="utf-8">
                <input type="hidden" name="data" value="%s" />
                <input type="hidden" name="signature" value="%s" />
                <input type="submit" value="Сплатити" class="btn btn-primary" />
            </form>',
            htmlspecialchars($data, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($signature, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Створює форму оплати для курсу
     */
    public function createCoursePaymentForm($user, $course, $payment)
    {
        try {
            // Перевірка типів параметрів
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
            
            // Генеруємо URL
            $callbackUrl = url('/api/payments/liqpay/callback');
            $returnUrl = url("/api/payments/course/{$course->id}/success");
            
            $description = "Оплата за курс: {$course->title}";
            
            $params = [
                'public_key'     => $this->publicKey,
                'action'         => 'pay',
                'amount'         => (string) $course->price,
                'currency'       => 'UAH',
                'description'    => $description,
                'order_id'       => $payment->id . '_' . Str::random(8),
                'version'        => '3',
                'language'       => 'uk',
                'sandbox'        => $this->sandbox ? '1' : '0',
                'server_url'     => $callbackUrl,
                'result_url'     => $returnUrl,
                'email'          => $user->email,
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
                'amount' => $course->price,
                'callback_url' => $callbackUrl,
                'return_url' => $returnUrl
            ]);
            
            // Генеруємо data та signature
            $data = base64_encode(json_encode($params));
            $signature = $this->generateSignature($data);
            
            return [
                'url' => 'https://www.liqpay.ua/api/3/checkout',
                'data' => $data,
                'signature' => $signature,
                'form_html' => $this->generateFormHtml($data, $signature),
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

            // Перевірка підпису
            $expectedSignature = $this->generateSignature($data['data']);
            
            if ($expectedSignature !== $data['signature']) {
                Log::error('LiqPay callback signature verification failed', [
                    'expected' => $expectedSignature,
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
            
            // Отримуємо додаткову інформацію
            $paymentInfo = json_decode($decodedData['info'] ?? '{}', true);
            
            // Знаходимо платіж
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
            
            // Знаходимо курс
            $course = Course::find($payment->entity_id);
            if (!$course) {
                Log::error('Course not found for payment', [
                    'payment_id' => $payment->id,
                    'course_id' => $payment->entity_id
                ]);
                return [
                    'status' => 'error',
                    'message' => 'Course not found'
                ];
            }
            
            Log::info('Processing LiqPay callback', [
                'payment_id' => $payment->id,
                'course_id' => $course->id,
                'liqpay_status' => $decodedData['status'] ?? 'unknown',
                'transaction_id' => $decodedData['transaction_id'] ?? null,
                'old_payment_status' => $payment->payment_status
            ]);
            
            // Оновлюємо дані платежу
            $oldStatus = $payment->payment_status;
            $payment->transaction_id = $decodedData['transaction_id'] ?? null;
            $payment->payment_status = $this->mapLiqPayStatus($decodedData['status'] ?? '');
            $payment->save();
            
            Log::info('Payment status updated', [
                'payment_id' => $payment->id,
                'old_status' => $oldStatus,
                'new_status' => $payment->payment_status,
                'liqpay_status' => $decodedData['status'] ?? 'unknown'
            ]);
            
            // Якщо платіж успішний - створюємо підписку
            if ($payment->payment_status === 'completed') {
                $enrollment = $this->createEnrollment($payment);
                
                if ($enrollment) {
                    Log::info('Enrollment created/updated successfully', [
                        'payment_id' => $payment->id,
                        'enrollment_id' => $enrollment->id,
                        'user_id' => $payment->user_id,
                        'course_id' => $course->id,
                        'enrollment_type' => $enrollment->enrollment_type
                    ]);
                }
                
                return [
                    'status' => 'success',
                    'payment' => $payment,
                    'enrollment' => $enrollment
                ];
            }
            
            return [
                'status' => 'pending',
                'payment' => $payment,
                'message' => "Payment status: {$payment->payment_status}"
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
     */
    protected function mapLiqPayStatus($liqpayStatus)
    {
        $statusMap = [
            'success' => 'completed',
            'wait_accept' => 'pending',
            'failure' => 'failed',
            'reversed' => 'refunded',
            'sandbox' => 'completed',
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
     */
    protected function createEnrollment(Payment $payment): ?CourseEnrollment
    {
        try {
            // Перевіряємо, чи це оплата за курс
            if ($payment->entity_type !== 'course') {
                Log::info('Payment is not for a course', [
                    'payment_id' => $payment->id, 
                    'entity_type' => $payment->entity_type
                ]);
                return null;
            }
            
            // Отримуємо курс та користувача
            $course = Course::find($payment->entity_id);
            $user = User::find($payment->user_id);
            
            if (!$course || !$user) {
                Log::error('Course or user not found for payment', [
                    'payment_id' => $payment->id,
                    'course_id' => $payment->entity_id,
                    'user_id' => $payment->user_id,
                    'course_exists' => $course ? true : false,
                    'user_exists' => $user ? true : false
                ]);
                return null;
            }
            
            // Перевіряємо існуючу підписку
            $existingEnrollment = CourseEnrollment::where('user_id', $payment->user_id)
                ->where('course_id', $payment->entity_id)
                ->first();
                
            if ($existingEnrollment) {
                // Оновлюємо існуючу підписку
                $existingEnrollment->update([
                    'payment_id' => $payment->id,
                    'enrollment_type' => 'purchase',
                    'is_active' => true,
                    'expires_at' => null,
                    'enrolled_at' => now(),
                ]);
                
                Log::info('Existing enrollment updated', [
                    'enrollment_id' => $existingEnrollment->id,
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'course_id' => $payment->entity_id
                ]);
                
                return $existingEnrollment;
            }
            
            // Створюємо нову підписку
            $enrollment = CourseEnrollment::create([
                'user_id' => $payment->user_id,
                'course_id' => $payment->entity_id,
                'enrolled_at' => now(),
                'expires_at' => null,
                'enrollment_type' => 'purchase',
                'payment_id' => $payment->id,
                'is_active' => true,
            ]);
            
            Log::info('New enrollment created', [
                'enrollment_id' => $enrollment->id,
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
                'course_id' => $payment->entity_id
            ]);
            
            // Відправка нотифікації
            try {
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
            }
            
            return $enrollment;
            
        } catch (\Exception $e) {
            Log::error('Error creating enrollment', [
                'payment_id' => $payment->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Перевірка статусу платежу в LiqPay
     */
    public function checkPaymentStatus($orderId)
    {
        try {
            $params = [
                'action' => 'status',
                'version' => '3',
                'public_key' => $this->publicKey,
                'order_id' => $orderId
            ];
            
            $data = base64_encode(json_encode($params));
            $signature = $this->generateSignature($data);
            
            // Виконуємо запит до LiqPay API
            $postData = http_build_query([
                'data' => $data,
                'signature' => $signature
            ]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $postData
                ]
            ]);
            
            $result = file_get_contents('https://www.liqpay.ua/api/request', false, $context);
            $result = json_decode($result, true);
            
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