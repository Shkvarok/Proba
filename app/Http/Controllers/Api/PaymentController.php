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
            'liqpay' => [
                'url' => $paymentForm['url'],
                'data' => $paymentForm['data'],
                'signature' => $paymentForm['signature'],
                'form_html' => $paymentForm['form_html']
            ]
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
            
            DB::beginTransaction();
            
            // Симуляція успішної оплати
            $payment->payment_status = 'completed';
            $payment->transaction_id = 'test_' . time();
            $payment->save();
            
            // Перевірка існуючої підписки
            $existingEnrollment = CourseEnrollment::where('user_id', $payment->user_id)
                ->where('course_id', $payment->entity_id)
                ->first();
            
            if ($existingEnrollment) {
                // Оновлюємо існуючу підписку
                $existingEnrollment->update([
                    'payment_id' => $payment->id,
                    'is_active' => true,
                    'enrollment_type' => 'purchase',
                    'expires_at' => null, // Безлімітний доступ для покупки
                ]);
                $enrollment = $existingEnrollment;
                
                Log::info('Existing enrollment updated', [
                    'enrollment_id' => $enrollment->id,
                    'payment_id' => $payment->id
                ]);
            } else {
                // Створення нової підписки
                $enrollment = CourseEnrollment::create([
                    'user_id' => $payment->user_id,
                    'course_id' => $payment->entity_id,
                    'is_active' => true,
                    'payment_id' => $payment->id,
                    'enrollment_type' => 'purchase',
                    'enrolled_at' => now(),
                ]);
                
                Log::info('New enrollment created', [
                    'enrollment_id' => $enrollment->id,
                    'payment_id' => $payment->id
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Тестовий callback успішно оброблено',
                'payment' => $payment,
                'enrollment' => $enrollment
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Test callback - model not found', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Платіж, курс або користувач не знайдено'
            ], 404);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Test callback error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при обробці тестового callback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Перегляд усіх оплат з фільтрами (адмін)
     * GET /api/payments/all
     */
    public function getPayments(Request $request)
{
    try {
        $query = Payment::with(['user:id,name,last_name,email']);

        // Фільтри
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->filled('course_id')) {
            $query->where('entity_type', 'course')->where('entity_id', $request->course_id);
        }
        
        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Отримуємо платежі з пагінацією
        $payments = $query->orderByDesc('created_at')->paginate(30);
        
        // Додаємо інформацію про курси для платежів за курси
        $payments->getCollection()->transform(function ($payment) {
            // Додаємо інформацію про пов'язану сутність
            if ($payment->entity_type === 'course') {
                $course = \App\Models\Course::find($payment->entity_id);
                $payment->course = $course ? [
                    'id' => $course->id,
                    'title' => $course->title,
                    'price' => $course->price
                ] : null;
            } else {
                $payment->course = null;
            }
            
            // Додаємо форматовану інформацію про користувача
            if ($payment->user) {
                $payment->user_name = trim($payment->user->name . ' ' . ($payment->user->last_name ?? ''));
            }
            
            return $payment;
        });

        return response()->json($payments);
        
    } catch (\Exception $e) {
        Log::error('Error in getPayments', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Помилка при отриманні платежів: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * Генерація фінансового звіту (адмін)
     * GET /api/payments/financial-report
     */
    public function getFinancialReport(Request $request)
    {
        try {
            $query = Payment::where('payment_status', 'completed');
    
            // Фільтри
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            
            if ($request->filled('course_id')) {
                $query->where('entity_type', 'course')->where('entity_id', $request->course_id);
            }
            
            if ($request->filled('from') || $request->filled('date_from')) {
                $dateFrom = $request->input('from') ?: $request->input('date_from');
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            
            if ($request->filled('to') || $request->filled('date_to')) {
                $dateTo = $request->input('to') ?: $request->input('date_to');
                $query->whereDate('created_at', '<=', $dateTo);
            }
    
            // Основна статистика
            $totalAmount = $query->sum('amount');
            $totalCount = $query->count();
            $averageAmount = $totalCount > 0 ? $totalAmount / $totalCount : 0;
    
            // Статистика по курсах
            $byCourse = $query->where('entity_type', 'course')
                ->select('entity_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
                ->groupBy('entity_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get();
    
            // Додаємо назви курсів
            $courseIds = $byCourse->pluck('entity_id');
            $courses = \App\Models\Course::whereIn('id', $courseIds)
                ->select('id', 'title')
                ->get()
                ->keyBy('id');
    
            $byCourse->transform(function ($item) use ($courses) {
                $course = $courses->get($item->entity_id);
                $item->course_title = $course ? $course->title : "Курс ID: {$item->entity_id}";
                return $item;
            });
    
            // Статистика по методах оплати
            $byPaymentMethod = $query->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
                ->groupBy('payment_method')
                ->get();
    
            // Статистика по місяцях
            $byMonth = $query->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('SUM(amount) as total'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get();
    
            return response()->json([
                'success' => true,
                'report' => [
                    'total_amount' => round($totalAmount, 2),
                    'total_count' => $totalCount,
                    'average_amount' => round($averageAmount, 2),
                    'by_course' => $byCourse,
                    'by_payment_method' => $byPaymentMethod,
                    'by_month' => $byMonth,
                    'period' => [
                        'from' => $request->input('from') ?: $request->input('date_from'),
                        'to' => $request->input('to') ?: $request->input('date_to'),
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Financial report error', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при генерації фінансового звіту',
                'error' => config('app.debug') ? $e->getMessage() : 'Внутрішня помилка сервера'
            ], 500);
        }
    }

    /**
     * Загальний звіт по продажах та по викладачах
     * GET /api/payments/sales-report
     */
    public function getSalesReport(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $payments = Payment::query()
            ->where('payment_status', 'completed')
            ->where('entity_type', 'course');

        if ($dateFrom) {
            $payments->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $payments->whereDate('created_at', '<=', $dateTo);
        }

        // Загальна сума та кількість продажів
        $totalAmount = $payments->sum('amount');
        $totalCount = $payments->count();

        // Сума і кількість по викладачах
        $byInstructor = Payment::query()
            ->select('courses.instructor_id', \DB::raw('SUM(payments.amount) as total'), \DB::raw('COUNT(payments.id) as count'))
            ->join('courses', 'payments.entity_id', '=', 'courses.id')
            ->where('payments.payment_status', 'completed')
            ->where('payments.entity_type', 'course');
        if ($dateFrom) {
            $byInstructor->whereDate('payments.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $byInstructor->whereDate('payments.created_at', '<=', $dateTo);
        }
        $byInstructor = $byInstructor->groupBy('courses.instructor_id')->get();

        return response()->json([
            'total_amount' => $totalAmount,
            'total_count' => $totalCount,
            'by_instructor' => $byInstructor,
        ]);
    }

    /**
     * Звіт по курсах (кількість і сума продажів по кожному курсу)
     * GET /api/payments/course-sales
     */
    public function getCourseSales(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $instructorId = $request->input('instructor_id');

        $query = Payment::query()
            ->select('courses.id as course_id', 'courses.title', 'courses.instructor_id', \DB::raw('SUM(payments.amount) as total'), \DB::raw('COUNT(payments.id) as count'))
            ->join('courses', 'payments.entity_id', '=', 'courses.id')
            ->where('payments.payment_status', 'completed')
            ->where('payments.entity_type', 'course');

        if ($dateFrom) {
            $query->whereDate('payments.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('payments.created_at', '<=', $dateTo);
        }
        if ($instructorId) {
            $query->where('courses.instructor_id', $instructorId);
        }

        $byCourse = $query->groupBy('courses.id', 'courses.title', 'courses.instructor_id')->get();

        return response()->json([
            'by_course' => $byCourse
        ]);
    }

    /**
     * Підтвердження оплати вручну (API)
     */
    public function confirmPayment(Request $request, $paymentId)
    {
        try {
            Log::info('Starting payment confirmation', ['payment_id' => $paymentId]);
            
            $user = Auth::user();
            Log::info('User authenticated', ['user_id' => $user->id]);
            
            $payment = Payment::findOrFail($paymentId);
            Log::info('Payment found', [
                'payment_id' => $payment->id,
                'current_status' => $payment->payment_status,
                'user_id' => $payment->user_id
            ]);

            // Перевірка, чи належить платіж поточному користувачу
            if ($payment->user_id !== $user->id) {
                Log::warning('Access denied for payment confirmation', [
                    'payment_id' => $paymentId,
                    'payment_user_id' => $payment->user_id,
                    'current_user_id' => $user->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ заборонено'
                ], 403);
            }

            // Якщо вже підтверджено
            if ($payment->payment_status === 'completed') {
                Log::info('Payment already completed', ['payment_id' => $paymentId]);
                return response()->json([
                    'success' => true,
                    'message' => 'Платіж вже підтверджено',
                    'payment' => $payment
                ]);
            }

            // Дозволяємо підтвердження лише для очікуючих/успішних платежів
            if (!in_array($payment->payment_status, ['pending', 'success'])) {
                Log::warning('Invalid payment status for confirmation', [
                    'payment_id' => $paymentId,
                    'current_status' => $payment->payment_status
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Платіж не може бути підтверджений у поточному статусі',
                    'payment' => $payment
                ], 400);
            }

            Log::info('Starting transaction for payment confirmation', ['payment_id' => $paymentId]);
            DB::beginTransaction();
            
            try {
                $payment->payment_status = 'completed';
                $payment->save();
                Log::info('Payment status updated', [
                    'payment_id' => $paymentId,
                    'new_status' => 'completed'
                ]);

                // Активуємо підписку, якщо ще не активна
                $enrollment = CourseEnrollment::where('user_id', $user->id)
                    ->where('course_id', $payment->entity_id)
                    ->first();
                    
                Log::info('Checking enrollment', [
                    'payment_id' => $paymentId,
                    'course_id' => $payment->entity_id,
                    'enrollment_exists' => $enrollment ? true : false
                ]);

                if (!$enrollment) {
                    Log::info('Creating new enrollment', [
                        'payment_id' => $paymentId,
                        'course_id' => $payment->entity_id
                    ]);
                    $enrollment = CourseEnrollment::create([
                        'user_id' => $user->id,
                        'course_id' => $payment->entity_id,
                        'is_active' => true,
                        'payment_id' => $payment->id,
                        'enrollment_type' => 'purchase',
                        'enrolled_at' => now(),
                    ]);
                } elseif (!$enrollment->is_active) {
                    Log::info('Activating existing enrollment', [
                        'enrollment_id' => $enrollment->id,
                        'payment_id' => $paymentId
                    ]);
                    $enrollment->is_active = true;
                    $enrollment->payment_id = $payment->id;
                    $enrollment->save();
                }

                DB::commit();
                Log::info('Payment confirmation completed successfully', [
                    'payment_id' => $paymentId,
                    'enrollment_id' => $enrollment->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Платіж підтверджено, доступ до курсу надано',
                    'payment' => $payment,
                    'enrollment' => $enrollment
                ]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error during payment confirmation transaction', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Payment not found', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Платіж не знайдено'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Payment confirmation error', [
                'payment_id' => $paymentId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Помилка при підтвердженні платежу: ' . $e->getMessage()
            ], 500);
        }
    }

}