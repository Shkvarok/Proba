<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Services\LiqPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use App\Models\User;

class PaymentController extends Controller
{
    protected $liqpayService;
    
    public function __construct(LiqPayService $liqpayService)
    {
        $this->liqpayService = $liqpayService;
    }
    
    /**
     * Create a course payment (alias for initiateCoursePayment)
     * This method is called by the route /payments/course/{courseId}
     */
    public function createCoursePayment(Request $request, $courseId)
    {
        return $this->initiateCoursePayment($request, $courseId);
    }
    
    /**
     * Ініціювати платіж за курс через LiqPay
     */
    public function initiateCoursePayment(Request $request, $courseId)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Користувач не автентифікований'
                ], 401);
            }
            
            $course = Course::findOrFail($courseId);
            
            // Перевірка, чи вже є активна підписка
            $existingEnrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('is_active', true)
                ->first();
                
            if ($existingEnrollment && $existingEnrollment->isActive()) {
                return response()->json([
                    'message' => 'Ви вже маєте доступ до цього курсу',
                    'enrollment' => $existingEnrollment
                ], 200);
            }
            
            // Перевірка, чи курс платний
            if ($course->price <= 0) {
                return response()->json([
                    'message' => 'Цей курс безкоштовний. Використайте відповідний ендпоінт для безкоштовного доступу.',
                ], 400);
            }
            
            DB::beginTransaction();
            
            // Створення запису про платіж в статусі pending
            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $course->price,
                'currency' => 'UAH',
                'payment_method' => 'liqpay',
                'payment_status' => 'pending',
                'entity_type' => 'course',
                'entity_id' => $course->id,
            ]);
            
            // Отримання форми оплати LiqPay
            // Забезпечуємо, що передаємо об'єкти, а не колекції
            $userObj = $user instanceof Collection ? $user->first() : $user;
            $courseObj = $course instanceof Collection ? $course->first() : $course;
            $paymentObj = $payment instanceof Collection ? $payment->first() : $payment;
            
            $paymentForm = $this->liqpayService->createCoursePaymentForm($userObj, $courseObj, $paymentObj);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Платіж ініційовано',
                'payment_id' => $payment->id,
                'liqpay_data' => $paymentForm
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Курс не знайдено'
            ], 404);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Payment initiation error', [
                'user_id' => Auth::id(),
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Помилка при ініціалізації платежу',
                'error' => config('app.debug') ? $e->getMessage() : 'Внутрішня помилка сервера'
            ], 500);
        }
    }
    
    /**
     * Обробка callback від LiqPay після оплати
     */
    public function liqpayCallback(Request $request)
    {
        try {
            // Отримуємо дані від LiqPay
            $data = $request->all();
            
            Log::info('LiqPay callback received', $data);
            
            // Обробка результату платежу
            $result = $this->liqpayService->processCallback($data);
            
            if ($result['status'] === 'error') {
                Log::error('LiqPay callback error', $result);
                return response('Error', 400);
            }
            
            if ($result['status'] === 'success') {
                Log::info('Payment successful', ['payment_id' => $result['payment']->id]);
            }
            
            // LiqPay очікує просту відповідь "ok"
            return response('ok');
            
        } catch (\Exception $e) {
            Log::error('LiqPay callback processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response('Error', 500);
        }
    }
    
    /**
     * Сторінка успішної оплати (куди перенаправляється користувач)
     */
    public function paymentSuccess(Request $request, $courseId)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Користувач не автентифікований'
                ], 401);
            }
            
            $course = Course::findOrFail($courseId);
            
            // Перевіряємо, чи є у користувача підписка на цей курс
            $enrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('is_active', true)
                ->first();
                
            if ($enrollment) {
                return response()->json([
                    'success' => true,
                    'message' => 'Оплата успішна! Ви отримали доступ до курсу.',
                    'enrollment' => $enrollment,
                    'course' => $course
                ]);
            }
            
            // Відстежуємо потенційну затримку у створенні підписки
            return response()->json([
                'success' => true,
                'message' => 'Оплата в обробці. Доступ до курсу буде надано найближчим часом.',
                'course' => $course
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Курс не знайдено'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Payment success page error', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при обробці результату оплати'
            ], 500);
        }
    }
    
    /**
     * Перевірка статусу платежу
     */
    public function checkPaymentStatus($paymentId)
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            
            // Перевірка, чи належить платіж поточному користувачу
            if ($payment->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Доступ заборонено'
                ], 403);
            }
            
            // Перевіряємо, чи створено підписку
            $enrollment = CourseEnrollment::where('payment_id', $payment->id)
                ->where('is_active', true)
                ->first();
                
            return response()->json([
                'payment' => $payment,
                'enrollment' => $enrollment,
                'has_access' => $enrollment !== null && $enrollment->isActive()
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Платіж не знайдено'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Check payment status error', [
                'payment_id' => $paymentId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Помилка при перевірці статусу платежу'
            ], 500);
        }
    }
    
    /**
     * Отримати історію платежів користувача
     */
    public function getUserPayments()
    {
        try {
            $payments = Auth::user()->payments()
                ->orderBy('created_at', 'desc')
                ->get();
                
            return response()->json(['payments' => $payments]);
            
        } catch (\Exception $e) {
            Log::error('Get user payments error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Помилка при отриманні історії платежів'
            ], 500);
        }
    }
    
    /**
     * Обробка невдалого платежу
     */
    public function paymentFailed(Request $request, $paymentId)
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            
            // Перевірка, чи належить платіж поточному користувачу (якщо користувач автентифікований)
            if (Auth::check() && $payment->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Доступ заборонено'
                ], 403);
            }
            
            // Якщо платіж уже в статусі "failed", просто повертаємо інформацію
            if ($payment->payment_status === 'failed') {
                return response()->json([
                    'message' => 'Платіж не вдалося обробити',
                    'payment' => $payment,
                ]);
            }
            
            return response()->json([
                'message' => 'Статус платежу: ' . $payment->payment_status,
                'payment' => $payment,
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Платіж не знайдено'
            ], 404);
        }
    }

    /**
     * Повторна спроба оплати
     */
    public function retryPayment(Request $request, $paymentId)
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            
            // Перевірка, чи належить платіж поточному користувачу
            if ($payment->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Доступ заборонено'
                ], 403);
            }
            
            // Перевірка, чи платіж невдалий або в очікуванні
            if (!in_array($payment->payment_status, ['failed', 'pending'])) {
                return response()->json([
                    'message' => 'Повторна спроба доступна тільки для невдалих платежів',
                    'payment' => $payment
                ], 400);
            }
            
            // Отримуємо інформацію про курс
            $course = Course::findOrFail($payment->entity_id);
            $user = Auth::user();
            
            // Змінюємо статус платежу на "pending"
            $payment->payment_status = 'pending';
            $payment->save();
            
            // Отримання форми оплати LiqPay
            // Забезпечуємо, що передаємо об'єкти, а не колекції
            $userObj = $user instanceof Collection ? $user->first() : $user;
            $courseObj = $course instanceof Collection ? $course->first() : $course;
            $paymentObj = $payment instanceof Collection ? $payment->first() : $payment;
            
            $paymentForm = $this->liqpayService->createCoursePaymentForm($userObj, $courseObj, $paymentObj);
            
            return response()->json([
                'success' => true,
                'message' => 'Платіж ініційовано',
                'payment_id' => $payment->id,
                'liqpay_data' => $paymentForm
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Платіж або курс не знайдено'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Payment retry error', [
                'payment_id' => $paymentId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Помилка при спробі повторити платіж',
                'error' => config('app.debug') ? $e->getMessage() : 'Внутрішня помилка сервера'
            ], 500);
        }
    }

    /**
     * Статистика платежів для адміністраторів
     */
    public function getPaymentStats()
    {
        try {
            $stats = [
                'total_payments' => Payment::count(),
                'completed_payments' => Payment::where('payment_status', 'completed')->count(),
                'pending_payments' => Payment::where('payment_status', 'pending')->count(),
                'failed_payments' => Payment::where('payment_status', 'failed')->count(),
                'total_revenue' => Payment::where('payment_status', 'completed')->sum('amount'),
                'recent_payments' => Payment::with(['user', 'entity'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
            ];
            
            return response()->json($stats);
            
        } catch (\Exception $e) {
            Log::error('Payment stats error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Помилка при отриманні статистики платежів'
            ], 500);
        }
    }

    /**
     * Тестовий метод для симуляції callback в контролері
     */
    public function testProcessCallback($paymentId) 
    {
        
        
        try {
            $payment = Payment::findOrFail($paymentId);
            $course = Course::findOrFail($payment->entity_id);
            $user = User::findOrFail($payment->user_id);
            
            // Симуляція успішної оплати
            $payment->payment_status = 'completed';
            $payment->transaction_id = 'test_' . time();
            $payment->save();
            
            // Створення підписки
            $enrollment = CourseEnrollment::create([
                'user_id' => $payment->user_id,
                'course_id' => $payment->entity_id,
                'enrollment_type' => 'purchase',
                'payment_id' => $payment->id,
                'is_active' => true,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Тестовий callback успішно оброблено',
                'payment' => $payment,
                'enrollment' => $enrollment
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Платіж, курс або користувач не знайдено'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Test callback error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при обробці тестового callback'
            ], 500);
        }
    }
}