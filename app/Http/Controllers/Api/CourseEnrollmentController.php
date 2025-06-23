<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CourseEnrollmentController extends Controller
{
    /**
     * Отримати всі підписки поточного користувача
     */
    public function index()
    {
        $user = Auth::user();
        
        $enrollments = $user->courseEnrollments()
            ->with(['course:id,title,price,cover_image', 'payment:id,amount,payment_status,created_at'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'enrollments' => $enrollments->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'course' => $enrollment->course,
                    'enrollment_type' => $enrollment->enrollment_type,
                    'is_active' => $enrollment->is_active,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'expires_at' => $enrollment->expires_at,
                    'remaining_days' => $enrollment->getRemainingDays(),
                    'payment' => $enrollment->payment,
                    'access_status' => $enrollment->isActive() ? 'active' : 'expired'
                ];
            })
        ]);
    }
    
    /**
     * Перевірити, чи має користувач доступ до курсу
     */
    public function checkAccess($courseId)
    {
        $user = Auth::user();
        
        try {
            $course = Course::findOrFail($courseId);
            
            // Перевіряємо роль користувача
            $hasAdminAccess = $user->hasAnyRole(['admin', 'super_admin', 'teacher']);
            
            // Перевіряємо підписку
            $enrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();
                
            $hasEnrollmentAccess = $enrollment && $enrollment->isActive();
            
            // Перевіряємо чи курс безкоштовний
            $isFree = $course->price <= 0;
            
            $hasAccess = $hasAdminAccess || $hasEnrollmentAccess || $isFree;
            
            $accessReason = null;
            if ($hasAdminAccess) {
                $accessReason = 'admin_access';
            } elseif ($hasEnrollmentAccess) {
                $accessReason = 'active_enrollment';
            } elseif ($isFree) {
                $accessReason = 'free_course';
            }
            
            Log::info('Course access check', [
                'user_id' => $user->id,
                'course_id' => $courseId,
                'has_access' => $hasAccess,
                'access_reason' => $accessReason,
                'has_admin_access' => $hasAdminAccess,
                'has_enrollment_access' => $hasEnrollmentAccess,
                'is_free' => $isFree,
                'enrollment_id' => $enrollment ? $enrollment->id : null
            ]);
            
            return response()->json([
                'success' => true,
                'has_access' => $hasAccess,
                'access_reason' => $accessReason,
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'price' => $course->price,
                    'is_free' => $isFree,
                    'is_published' => $course->is_published
                ],
                'enrollment' => $enrollment ? [
                    'id' => $enrollment->id,
                    'is_active' => $enrollment->is_active,
                    'enrollment_type' => $enrollment->enrollment_type,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'expires_at' => $enrollment->expires_at,
                    'remaining_days' => $enrollment->getRemainingDays(),
                    'payment_id' => $enrollment->payment_id
                ] : null,
                'actions' => [
                    'can_purchase' => !$hasAccess && !$isFree && $course->price > 0,
                    'can_enroll_free' => !$hasAccess && $isFree,
                    'purchase_url' => !$hasAccess && !$isFree ? route('payments.course.create', $courseId) : null,
                    'free_enroll_url' => !$hasAccess && $isFree ? route('enrollments.free', $courseId) : null
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Курс не знайдено'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error checking course access', [
                'user_id' => $user->id,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при перевірці доступу до курсу'
            ], 500);
        }
    }
    
    /**
     * Надати безкоштовний доступ до курсу
     */
    public function enrollFree(Request $request, $courseId)
    {
        $user = Auth::user();
        
        try {
            $course = Course::findOrFail($courseId);
            
            // Перевірка, чи курс безкоштовний
            if ($course->price > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Цей курс не є безкоштовним',
                    'course_price' => $course->price
                ], 400);
            }
            
            // Перевірка, чи вже є підписка
            $existingEnrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();
                
            if ($existingEnrollment) {
                if ($existingEnrollment->isActive()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Ви вже маєте доступ до цього курсу',
                        'enrollment' => $existingEnrollment
                    ]);
                } else {
                    // Реактивуємо існуючу підписку
                    $existingEnrollment->update([
                        'is_active' => true,
                        'enrolled_at' => now(),
                        'enrollment_type' => 'free'
                    ]);
                    
                    Log::info('Free enrollment reactivated', [
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'enrollment_id' => $existingEnrollment->id
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Доступ до курсу відновлено',
                        'enrollment' => $existingEnrollment
                    ]);
                }
            }
            
            // Створення нової підписки
            $enrollment = CourseEnrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'enrollment_type' => 'free',
                'is_active' => true,
                'enrolled_at' => now(),
                'expires_at' => null
            ]);
            
            Log::info('Free enrollment created', [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'enrollment_id' => $enrollment->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Ви успішно підписалися на безкоштовний курс',
                'enrollment' => $enrollment
            ], 201);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Курс не знайдено'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error creating free enrollment', [
                'user_id' => $user->id,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при підписці на курс'
            ], 500);
        }
    }
    
    /**
     * Отримати детальну інформацію про підписку
     */
    public function getEnrollmentDetails($courseId)
    {
        $user = Auth::user();
        
        try {
            $course = Course::findOrFail($courseId);
            
            $enrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->with(['payment:id,amount,payment_status,transaction_id,created_at'])
                ->first();
                
            if (!$enrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Підписку не знайдено'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'enrollment' => [
                    'id' => $enrollment->id,
                    'course_id' => $enrollment->course_id,
                    'enrollment_type' => $enrollment->enrollment_type,
                    'is_active' => $enrollment->is_active,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'expires_at' => $enrollment->expires_at,
                    'remaining_days' => $enrollment->getRemainingDays(),
                    'access_status' => $enrollment->isActive() ? 'active' : 'expired',
                    'payment' => $enrollment->payment
                ],
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'price' => $course->price
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Курс не знайдено'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error getting enrollment details', [
                'user_id' => $user->id,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні деталей підписки'
            ], 500);
        }
    }
}