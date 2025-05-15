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
     * Ініціювати платіж за курс через LiqPay
     */
    public function initiateCoursePayment(Request $request, $courseId)
    {
        $user = Auth::user();
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
            ]);
        }
        
        try {
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
                'message' => 'Платіж ініційовано',
                'payment_id' => $payment->id,
                'liqpay_data' => $paymentForm
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment initiation error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'exception' => $e
            ]);
            
            return response()->json([
                'message' => 'Помилка при ініціалізації платежу',
                'error' => $e->getMessage()
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
            Log::error('LiqPay callback processing error: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            
            return response('Error', 500);
        }
    }
    
    /**
     * Сторінка успішної оплати (куди перенаправляється користувач)
     */
    public function paymentSuccess(Request $request, $courseId)
    {
        $user = Auth::user();
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
    }
    
    /**
     * Перевірка статусу платежу
     */
    public function checkPaymentStatus($paymentId)
    {
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
    }
    
    /**
     * Отримати історію платежів користувача
     */
    public function getUserPayments()
    {
        $payments = Auth::user()->payments()
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json(['payments' => $payments]);
    }
    
    /**
     * Обробка невдалого платежу
     */
    public function paymentFailed(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        
        // Перевірка, чи належить платіж поточному користувачу
        if ($payment->user_id !== Auth::id()) {
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
    }

    /**
     * Повторна спроба оплати
     */
    public function retryPayment(Request $request, $paymentId)
    {
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
        
        try {
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
                'message' => 'Платіж ініційовано',
                'payment_id' => $payment->id,
                'liqpay_data' => $paymentForm
            ]);
            
        } catch (\Exception $e) {
            Log::error('Payment retry error: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'exception' => $e
            ]);
            
            return response()->json([
                'message' => 'Помилка при спробі повторити платіж',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Тимчасовий код для симуляції callback в контролері
public function testProcessCallback($paymentId) {
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
}
}