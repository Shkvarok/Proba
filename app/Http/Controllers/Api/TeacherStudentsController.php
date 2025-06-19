<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Models\CourseEnrollment;
use App\Models\LessonProgress;
use App\Http\Resources\LessonProgressResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class TeacherStudentsController extends Controller
{
    /**
     * Отримати всіх студентів, які мають доступ до курсів вчителя
     * GET /api/teacher/students
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'course_id' => 'nullable|integer|exists:courses,id',
                'search' => 'nullable|string|max:100',
                'status' => 'nullable|in:active,inactive,completed,in_progress',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|in:name,email,enrolled_at,last_activity,progress',
                'sort_direction' => 'nullable|in:asc,desc'
            ]);

            $teacherId = auth()->id();
            $perPage = min($request->input('per_page', 15), 100);
            $courseId = $request->input('course_id');
            $search = $request->input('search');
            $status = $request->input('status');
            $sortBy = $request->input('sort_by', 'enrolled_at');
            $sortDirection = $request->input('sort_direction', 'desc');

            Log::info('TeacherStudentsController@index: Початок методу', [
                'teacher_id' => $teacherId,
                'params' => $request->all()
            ]);

            // Базовий запит для отримання студентів
            $query = User::select([
                    'users.id',
                    'users.name',
                    'users.last_name', 
                    'users.email',
                    'users.created_at',
                    'course_enrollments.enrolled_at',
                    'course_enrollments.course_id',
                    'course_enrollments.is_active as enrollment_is_active',
                    'course_enrollments.expires_at',
                    'courses.title as course_title'
                ])
                ->join('course_enrollments', 'users.id', '=', 'course_enrollments.user_id')
                ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
                ->where('courses.instructor_id', $teacherId)
                ->where('course_enrollments.is_active', true);

            // Фільтр по конкретному курсу
            if ($courseId) {
                // Перевіряємо, чи належить курс цьому вчителю
                $course = Course::where('id', $courseId)
                    ->where('instructor_id', $teacherId)
                    ->first();
                    
                if (!$course) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Курс не знайдено або у вас немає до нього доступу'
                    ], 404);
                }
                
                $query->where('course_enrollments.course_id', $courseId);
            }

            // Пошук по імені або email
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('users.name', 'LIKE', "%{$search}%")
                      ->orWhere('users.last_name', 'LIKE', "%{$search}%")
                      ->orWhere('users.email', 'LIKE', "%{$search}%");
                });
            }

            // Фільтр по статусу (буде реалізовано нижче через додаткові запити)
            
            // Сортування
            switch ($sortBy) {
                case 'name':
                    $query->orderBy('users.name', $sortDirection);
                    break;
                case 'email':
                    $query->orderBy('users.email', $sortDirection);
                    break;
                case 'enrolled_at':
                    $query->orderBy('course_enrollments.enrolled_at', $sortDirection);
                    break;
                default:
                    $query->orderBy('course_enrollments.enrolled_at', $sortDirection);
            }

            $students = $query->paginate($perPage);

            // Додаємо прогрес для кожного студента
            $studentsWithProgress = $students->getCollection()->map(function($student) use ($status) {
                // Отримуємо прогрес по курсу
                $courseProgress = $this->getCourseProgressForStudent($student->id, $student->course_id);
                
                // Визначаємо статус студента
                $studentStatus = $this->determineStudentStatus($student, $courseProgress);
                
                // Застосовуємо фільтр по статусу
                if ($status && $studentStatus !== $status) {
                    return null; // Буде відфільтровано нижче
                }

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'last_name' => $student->last_name,
                    'full_name' => trim($student->name . ' ' . $student->last_name),
                    'email' => $student->email,
                    'course_id' => $student->course_id,
                    'course_title' => $student->course_title,
                    'enrolled_at' => $student->enrolled_at,
                    'enrollment_is_active' => $student->enrollment_is_active,
                    'expires_at' => $student->expires_at,
                    'status' => $studentStatus,
                    'progress' => $courseProgress,
                    'last_activity' => $this->getLastActivity($student->id, $student->course_id),
                ];
            })->filter(); // Видаляємо null значення після фільтрації по статусу

            // Якщо застосовувався фільтр по статусу, оновлюємо колекцію
            $students->setCollection($studentsWithProgress->values());

            Log::info('TeacherStudentsController@index: Студентів отримано', [
                'count' => $students->count(),
                'teacher_id' => $teacherId
            ]);

            return response()->json([
                'success' => true,
                'students' => $students,
                'meta' => [
                    'total_courses' => $this->getTeacherCoursesCount($teacherId),
                    'total_active_students' => $this->getActiveStudentsCount($teacherId),
                ]
            ]);

        } catch (Exception $e) {
            Log::error('TeacherStudentsController@index: Помилка', [
                'teacher_id' => auth()->id(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні списку студентів: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати детальну інформацію про студента та його прогрес
     * GET /api/teacher/students/{studentId}
     */
    public function show(Request $request, int $studentId): JsonResponse
    {
        try {
            $request->validate([
                'course_id' => 'nullable|integer|exists:courses,id'
            ]);

            $teacherId = auth()->id();
            $courseId = $request->input('course_id');

            Log::info('TeacherStudentsController@show: Початок методу', [
                'teacher_id' => $teacherId,
                'student_id' => $studentId,
                'course_id' => $courseId
            ]);

            // Знаходимо студента
            $student = User::find($studentId);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Студента не знайдено'
                ], 404);
            }

            // Перевіряємо, чи має вчитель доступ до цього студента
            $enrollmentsQuery = CourseEnrollment::where('user_id', $studentId)
                ->whereHas('course', function($query) use ($teacherId) {
                    $query->where('instructor_id', $teacherId);
                })
                ->where('is_active', true);

            if ($courseId) {
                // Перевіряємо, чи належить курс цьому вчителю
                $course = Course::where('id', $courseId)
                    ->where('instructor_id', $teacherId)
                    ->first();
                    
                if (!$course) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Курс не знайдено або у вас немає до нього доступу'
                    ], 404);
                }
                
                $enrollmentsQuery->where('course_id', $courseId);
            }

            $enrollments = $enrollmentsQuery->with('course')->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас немає доступу до цього студента або він не записаний на ваші курси'
                ], 403);
            }

            // Збираємо детальну інформацію
            $studentData = [
                'id' => $student->id,
                'name' => $student->name,
                'last_name' => $student->last_name,
                'full_name' => trim($student->name . ' ' . $student->last_name),
                'email' => $student->email,
                'created_at' => $student->created_at,
                'enrollments' => []
            ];

            // Додаємо інформацію про кожний курс
            foreach ($enrollments as $enrollment) {
                $courseProgress = $this->getCourseProgressForStudent($studentId, $enrollment->course_id);
                $detailedProgress = $this->getDetailedCourseProgress($studentId, $enrollment->course_id);

                $studentData['enrollments'][] = [
                    'course_id' => $enrollment->course_id,
                    'course_title' => $enrollment->course->title,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'expires_at' => $enrollment->expires_at,
                    'is_active' => $enrollment->is_active,
                    'status' => $this->determineStudentStatus($enrollment, $courseProgress),
                    'progress' => $courseProgress,
                    'detailed_progress' => $detailedProgress,
                    'last_activity' => $this->getLastActivity($studentId, $enrollment->course_id),
                    'time_spent_total' => $this->getTotalTimeSpent($studentId, $enrollment->course_id),
                ];
            }

            Log::info('TeacherStudentsController@show: Інформацію про студента отримано', [
                'student_id' => $studentId,
                'enrollments_count' => $enrollments->count()
            ]);

            return response()->json([
                'success' => true,
                'student' => $studentData
            ]);

        } catch (Exception $e) {
            Log::error('TeacherStudentsController@show: Помилка', [
                'teacher_id' => auth()->id(),
                'student_id' => $studentId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні інформації про студента: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати прогрес студента по конкретному уроку
     * GET /api/teacher/students/{studentId}/lessons/{lessonId}/progress
     */
    public function getLessonProgress(int $studentId, int $lessonId): JsonResponse
    {
        try {
            $teacherId = auth()->id();

            // Перевіряємо, чи належить урок курсу цього вчителя
            $lesson = \App\Models\Lesson::whereHas('module.course', function($query) use ($teacherId) {
                $query->where('instructor_id', $teacherId);
            })->find($lessonId);

            if (!$lesson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Урок не знайдено або у вас немає до нього доступу'
                ], 404);
            }

            // Перевіряємо, чи має студент доступ до цього курсу
            $hasAccess = CourseEnrollment::where('user_id', $studentId)
                ->where('course_id', $lesson->module->course_id)
                ->where('is_active', true)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Студент не має доступу до цього курсу'
                ], 403);
            }

            // Отримуємо прогрес уроку
            $progress = LessonProgress::where('user_id', $studentId)
                ->where('lesson_id', $lessonId)
                ->with(['lesson.module.course'])
                ->first();

            $lessonData = [
                'lesson_id' => $lesson->id,
                'lesson_title' => $lesson->title,
                'lesson_type' => $lesson->type,
                'module_title' => $lesson->module->title,
                'course_title' => $lesson->module->course->title,
                'progress' => $progress ? new LessonProgressResource($progress) : [
                    'is_completed' => false,
                    'progress_percentage' => 0,
                    'time_spent' => 0,
                    'formatted_time_spent' => '00:00',
                    'started_at' => null,
                    'completed_at' => null,
                    'last_accessed_at' => null,
                    'progress_status' => 'not_started'
                ]
            ];

            return response()->json([
                'success' => true,
                'lesson_progress' => $lessonData
            ]);

        } catch (Exception $e) {
            Log::error('TeacherStudentsController@getLessonProgress: Помилка', [
                'teacher_id' => auth()->id(),
                'student_id' => $studentId,
                'lesson_id' => $lessonId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні прогресу уроку: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати статистику студентів для вчителя
     * GET /api/teacher/students/statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $teacherId = auth()->id();

            $stats = [
                'total_courses' => $this->getTeacherCoursesCount($teacherId),
                'total_students' => $this->getTotalStudentsCount($teacherId),
                'active_students' => $this->getActiveStudentsCount($teacherId),
                'completed_courses' => $this->getCompletedCoursesCount($teacherId),
                'average_completion_rate' => $this->getAverageCompletionRate($teacherId),
                'students_by_status' => $this->getStudentsByStatus($teacherId),
                'recent_enrollments' => $this->getRecentEnrollments($teacherId),
                'top_performing_students' => $this->getTopPerformingStudents($teacherId),
            ];

            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);

        } catch (Exception $e) {
            Log::error('TeacherStudentsController@getStatistics: Помилка', [
                'teacher_id' => auth()->id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні статистики: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отримати список курсів вчителя з кількістю студентів
     * GET /api/teacher/courses-overview
     */
    public function getCoursesOverview(): JsonResponse
    {
        try {
            $teacherId = auth()->id();

            $courses = Course::where('instructor_id', $teacherId)
                ->withCount([
                    'enrollments as active_students_count' => function($query) {
                        $query->where('is_active', true);
                    }
                ])
                ->with([
                    'enrollments' => function($query) {
                        $query->where('is_active', true)
                              ->with('user:id,name,last_name,email');
                    }
                ])
                ->get()
                ->map(function($course) {
                    $averageProgress = $this->getCourseAverageProgress($course->id);
                    
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'is_published' => $course->is_published,
                        'students_count' => $course->active_students_count,
                        'average_progress' => $averageProgress,
                        'recent_students' => $course->enrollments->take(5)->map(function($enrollment) {
                            return [
                                'id' => $enrollment->user->id,
                                'name' => $enrollment->user->name . ' ' . $enrollment->user->last_name,
                                'email' => $enrollment->user->email,
                                'enrolled_at' => $enrollment->enrolled_at,
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'courses' => $courses
            ]);

        } catch (Exception $e) {
            Log::error('TeacherStudentsController@getCoursesOverview: Помилка', [
                'teacher_id' => auth()->id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні огляду курсів: ' . $e->getMessage()
            ], 500);
        }
    }

    // Приватні методи для обчислення статистики

    private function getCourseProgressForStudent(int $studentId, int $courseId): array
    {
        try {
            // Отримуємо загальну кількість уроків в курсі
            $totalLessons = DB::table('lessons')
                ->join('modules', 'lessons.module_id', '=', 'modules.id')
                ->where('modules.course_id', $courseId)
                ->count();

            if ($totalLessons === 0) {
                return [
                    'total_lessons' => 0,
                    'completed_lessons' => 0,
                    'progress_percentage' => 0,
                    'is_completed' => false
                ];
            }

            // Отримуємо кількість завершених уроків
            $completedLessons = LessonProgress::whereHas('lesson.module', function($query) use ($courseId) {
                    $query->where('course_id', $courseId);
                })
                ->where('user_id', $studentId)
                ->completed()
                ->count();

            $progressPercentage = round(($completedLessons / $totalLessons) * 100, 1);

            return [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress_percentage' => $progressPercentage,
                'is_completed' => $progressPercentage >= 100
            ];

        } catch (Exception $e) {
            Log::error('getCourseProgressForStudent: Помилка', [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return [
                'total_lessons' => 0,
                'completed_lessons' => 0,
                'progress_percentage' => 0,
                'is_completed' => false
            ];
        }
    }

    private function getDetailedCourseProgress(int $studentId, int $courseId): array
    {
        try {
            $modules = DB::table('modules')
                ->where('course_id', $courseId)
                ->orderBy('position')
                ->get();

            $detailedProgress = [];

            foreach ($modules as $module) {
                $lessons = DB::table('lessons')
                    ->where('module_id', $module->id)
                    ->orderBy('position')
                    ->get();

                $moduleProgress = [
                    'module_id' => $module->id,
                    'module_title' => $module->title,
                    'lessons' => []
                ];

                foreach ($lessons as $lesson) {
                    $progress = LessonProgress::forUser($studentId)
                        ->forLesson($lesson->id)
                        ->first();

                    $moduleProgress['lessons'][] = [
                        'lesson_id' => $lesson->id,
                        'lesson_title' => $lesson->title,
                        'lesson_type' => $lesson->type,
                        'is_completed' => $progress ? $progress->is_completed : false,
                        'progress_percentage' => $progress ? $progress->progress_percentage : 0,
                        'time_spent' => $progress ? $progress->time_spent : 0,
                        'formatted_time_spent' => $progress ? $progress->formatted_time_spent : '00:00',
                        'last_accessed_at' => $progress ? $progress->last_accessed_at : null,
                        'progress_status' => $progress ? ($progress->is_completed ? 'completed' : ($progress->started_at ? 'in_progress' : 'not_started')) : 'not_started'
                    ];
                }

                $detailedProgress[] = $moduleProgress;
            }

            return $detailedProgress;

        } catch (Exception $e) {
            Log::error('getDetailedCourseProgress: Помилка', [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    private function determineStudentStatus($enrollment, array $progress): string
    {
        if (!$enrollment->enrollment_is_active) {
            return 'inactive';
        }

        if ($progress['is_completed']) {
            return 'completed';
        }

        if ($progress['completed_lessons'] > 0) {
            return 'in_progress';
        }

        return 'active';
    }

    private function getLastActivity(int $studentId, int $courseId): ?string
    {
        $lastProgress = LessonProgress::whereHas('lesson.module', function($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->where('user_id', $studentId)
            ->orderBy('last_accessed_at', 'desc')
            ->first();

        return $lastProgress ? $lastProgress->last_accessed_at->toISOString() : null;
    }

    private function getTotalTimeSpent(int $studentId, int $courseId): int
    {
        return LessonProgress::whereHas('lesson.module', function($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->where('user_id', $studentId)
            ->sum('time_spent') ?? 0;
    }

    private function getTeacherCoursesCount(int $teacherId): int
    {
        return Course::where('instructor_id', $teacherId)->count();
    }

    private function getTotalStudentsCount(int $teacherId): int
    {
        return DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.instructor_id', $teacherId)
            ->where('course_enrollments.is_active', true)
            ->distinct('course_enrollments.user_id')
            ->count();
    }

    private function getActiveStudentsCount(int $teacherId): int
    {
        return $this->getTotalStudentsCount($teacherId); // Поки що те ж саме
    }

    private function getCompletedCoursesCount(int $teacherId): int
    {
        // Підраховуємо студентів, які завершили хоча б один курс цього вчителя
        $courses = Course::where('instructor_id', $teacherId)->pluck('id');
        
        $completedCount = 0;
        foreach ($courses as $courseId) {
            $enrollments = CourseEnrollment::where('course_id', $courseId)
                ->where('is_active', true)
                ->get();
                
            foreach ($enrollments as $enrollment) {
                $progress = $this->getCourseProgressForStudent($enrollment->user_id, $courseId);
                if ($progress['is_completed']) {
                    $completedCount++;
                }
            }
        }
        
        return $completedCount;
    }

    private function getAverageCompletionRate(int $teacherId): float
    {
        $courses = Course::where('instructor_id', $teacherId)->pluck('id');
        
        if ($courses->isEmpty()) {
            return 0.0;
        }
        
        $totalRate = 0;
        $studentCount = 0;
        
        foreach ($courses as $courseId) {
            $enrollments = CourseEnrollment::where('course_id', $courseId)
                ->where('is_active', true)
                ->get();
                
            foreach ($enrollments as $enrollment) {
                $progress = $this->getCourseProgressForStudent($enrollment->user_id, $courseId);
                $totalRate += $progress['progress_percentage'];
                $studentCount++;
            }
        }
        
        return $studentCount > 0 ? round($totalRate / $studentCount, 1) : 0.0;
    }

    private function getStudentsByStatus(int $teacherId): array
    {
        $statuses = ['active' => 0, 'in_progress' => 0, 'completed' => 0, 'inactive' => 0];
        
        $enrollments = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->where('courses.instructor_id', $teacherId)
            ->get();
            
        foreach ($enrollments as $enrollment) {
            $progress = $this->getCourseProgressForStudent($enrollment->user_id, $enrollment->course_id);
            $status = $this->determineStudentStatus($enrollment, $progress);
            $statuses[$status]++;
        }
        
        return $statuses;
    }

    private function getRecentEnrollments(int $teacherId, int $limit = 5): array
    {
        return DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->join('users', 'course_enrollments.user_id', '=', 'users.id')
            ->where('courses.instructor_id', $teacherId)
            ->where('course_enrollments.is_active', true)
            ->orderBy('course_enrollments.enrolled_at', 'desc')
            ->limit($limit)
            ->select([
                'users.name',
                'users.last_name',
                'users.email',
                'courses.title as course_title',
                'course_enrollments.enrolled_at'
            ])
            ->get()
            ->toArray();
    }

    private function getTopPerformingStudents(int $teacherId, int $limit = 5): array
    {
        // Тут можна реалізувати складнішу логіку для визначення топ студентів
        // Поки що повертаємо студентів з найвищим відсотком завершення
        $students = [];
        
        $enrollments = DB::table('course_enrollments')
            ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
            ->join('users', 'course_enrollments.user_id', '=', 'users.id')
            ->where('courses.instructor_id', $teacherId)
            ->where('course_enrollments.is_active', true)
            ->select([
                'users.id',
                'users.name',
                'users.last_name',
                'users.email',
                'course_enrollments.course_id',
                'courses.title as course_title'
            ])
            ->get();
            
        foreach ($enrollments as $enrollment) {
            $progress = $this->getCourseProgressForStudent($enrollment->id, $enrollment->course_id);
            
            $students[] = [
                'student_id' => $enrollment->id,
                'name' => $enrollment->name . ' ' . $enrollment->last_name,
                'email' => $enrollment->email,
                'course_title' => $enrollment->course_title,
                'progress_percentage' => $progress['progress_percentage']
            ];
        }
        
        // Сортуємо по прогресу та повертаємо топ
        usort($students, function($a, $b) {
            return $b['progress_percentage'] <=> $a['progress_percentage'];
        });
        
        return array_slice($students, 0, $limit);
    }

    /**
     * Огляд всіх вчителів та їхніх студентів (тільки для адмінів)
     * GET /api/admin/analytics/teachers-overview
     */
    public function getTeachersOverview(): JsonResponse
    {
        try {
            $teachers = User::whereHas('role', function($query) {
                    $query->where('name', 'teacher');
                })
                ->withCount([
                    'courses as total_courses',
                    'courses as published_courses' => function($query) {
                        $query->where('is_published', true);
                    }
                ])
                ->with([
                    'courses' => function($query) {
                        $query->withCount([
                            'enrollments as students_count' => function($q) {
                                $q->where('is_active', true);
                            }
                        ]);
                    }
                ])
                ->get()
                ->map(function($teacher) {
                    $totalStudents = $teacher->courses->sum('students_count');
                    $averageProgress = $this->getTeacherAverageProgress($teacher->id);
                    
                    return [
                        'id' => $teacher->id,
                        'name' => $teacher->name . ' ' . $teacher->last_name,
                        'email' => $teacher->email,
                        'total_courses' => $teacher->total_courses,
                        'published_courses' => $teacher->published_courses,
                        'total_students' => $totalStudents,
                        'average_student_progress' => $averageProgress,
                        'created_at' => $teacher->created_at,
                        'courses' => $teacher->courses->map(function($course) {
                            return [
                                'id' => $course->id,
                                'title' => $course->title,
                                'is_published' => $course->is_published,
                                'students_count' => $course->students_count
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'teachers' => $teachers,
                'summary' => [
                    'total_teachers' => $teachers->count(),
                    'total_courses' => $teachers->sum('total_courses'),
                    'total_students' => $teachers->sum('total_students'),
                    'average_students_per_teacher' => $teachers->count() > 0 ? 
                        round($teachers->sum('total_students') / $teachers->count(), 1) : 0
                ]
            ]);

        } catch (Exception $e) {
            Log::error('TeacherStudentsController@getTeachersOverview: Помилка', [
                'message' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні огляду вчителів: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Детальна статистика курсу (тільки для адмінів)
     * GET /api/admin/analytics/courses/{courseId}/detailed-stats
     */
    public function getCourseDetailedStats(int $courseId): JsonResponse
    {
        try {
            $course = Course::with(['instructor', 'modules.lessons'])
                ->findOrFail($courseId);

            $enrollments = CourseEnrollment::where('course_id', $courseId)
                ->where('is_active', true)
                ->with('user')
                ->get();

            $studentsStats = [];
            $totalProgress = 0;
            $completedStudents = 0;

            foreach ($enrollments as $enrollment) {
                $progress = $this->getCourseProgressForStudent($enrollment->user_id, $courseId);
                
                if ($progress['is_completed']) {
                    $completedStudents++;
                }
                
                $totalProgress += $progress['progress_percentage'];
                
                $studentsStats[] = [
                    'student_id' => $enrollment->user_id,
                    'student_name' => $enrollment->user->name . ' ' . $enrollment->user->last_name,
                    'student_email' => $enrollment->user->email,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'progress' => $progress,
                    'last_activity' => $this->getLastActivity($enrollment->user_id, $courseId),
                    'total_time_spent' => $this->getTotalTimeSpent($enrollment->user_id, $courseId)
                ];
            }

            // Статистика по урокам
            $lessonsStats = [];
            foreach ($course->modules as $module) {
                foreach ($module->lessons as $lesson) {
                    $lessonStats = $lesson->getCompletionStats();
                    $lessonsStats[] = [
                        'lesson_id' => $lesson->id,
                        'lesson_title' => $lesson->title,
                        'lesson_type' => $lesson->type,
                        'module_title' => $module->title,
                        'completion_stats' => $lessonStats
                    ];
                }
            }

            $averageProgress = $enrollments->count() > 0 ? 
                round($totalProgress / $enrollments->count(), 1) : 0;

            return response()->json([
                'success' => true,
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'instructor' => [
                        'id' => $course->instructor->id,
                        'name' => $course->instructor->name . ' ' . $course->instructor->last_name,
                        'email' => $course->instructor->email
                    ],
                    'created_at' => $course->created_at
                ],
                'statistics' => [
                    'total_students' => $enrollments->count(),
                    'completed_students' => $completedStudents,
                    'completion_rate' => $enrollments->count() > 0 ? 
                        round(($completedStudents / $enrollments->count()) * 100, 1) : 0,
                    'average_progress' => $averageProgress,
                    'total_lessons' => $course->modules->sum(function($module) {
                        return $module->lessons->count();
                    })
                ],
                'students' => $studentsStats,
                'lessons_statistics' => $lessonsStats
            ]);

        } catch (Exception $e) {
            Log::error('TeacherStudentsController@getCourseDetailedStats: Помилка', [
                'course_id' => $courseId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при отриманні детальної статистики курсу: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Експорт даних студентів у CSV
     * GET /api/admin/analytics/students/export
     */
    public function exportStudentsData(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'course_id' => 'nullable|integer|exists:courses,id',
                'teacher_id' => 'nullable|integer|exists:users,id',
                'format' => 'nullable|in:csv,json',
                'include_progress' => 'nullable|boolean'
            ]);

            $format = $request->input('format', 'csv');
            $courseId = $request->input('course_id');
            $teacherId = $request->input('teacher_id');
            $includeProgress = $request->boolean('include_progress', true);

            // Базовий запит для отримання студентів
            $query = DB::table('course_enrollments')
                ->join('courses', 'course_enrollments.course_id', '=', 'courses.id')
                ->join('users', 'course_enrollments.user_id', '=', 'users.id')
                ->leftJoin('users as instructors', 'courses.instructor_id', '=', 'instructors.id')
                ->where('course_enrollments.is_active', true)
                ->select([
                    'users.id as student_id',
                    'users.name as student_name',
                    'users.last_name as student_last_name',
                    'users.email as student_email',
                    'courses.id as course_id',
                    'courses.title as course_title',
                    'instructors.name as instructor_name',
                    'instructors.last_name as instructor_last_name',
                    'course_enrollments.enrolled_at',
                    'course_enrollments.expires_at'
                ]);

            if ($courseId) {
                $query->where('course_enrollments.course_id', $courseId);
            }

            if ($teacherId) {
                $query->where('courses.instructor_id', $teacherId);
            }

            $data = $query->get()->map(function($row) use ($includeProgress) {
                $exportRow = [
                    'student_id' => $row->student_id,
                    'student_name' => $row->student_name . ' ' . $row->student_last_name,
                    'student_email' => $row->student_email,
                    'course_id' => $row->course_id,
                    'course_title' => $row->course_title,
                    'instructor_name' => $row->instructor_name . ' ' . $row->instructor_last_name,
                    'enrolled_at' => $row->enrolled_at,
                    'expires_at' => $row->expires_at
                ];

                if ($includeProgress) {
                    $progress = $this->getCourseProgressForStudent($row->student_id, $row->course_id);
                    $exportRow = array_merge($exportRow, [
                        'total_lessons' => $progress['total_lessons'],
                        'completed_lessons' => $progress['completed_lessons'],
                        'progress_percentage' => $progress['progress_percentage'],
                        'is_completed' => $progress['is_completed'] ? 'Так' : 'Ні',
                        'total_time_spent' => $progress['total_time_spent'],
                        'last_activity' => $this->getLastActivity($row->student_id, $row->course_id)
                    ]);
                }

                return $exportRow;
            });

            if ($format === 'csv') {
                // Тут можна було б генерувати CSV файл та повертати його
                // Поки що повертаємо дані для подальшої обробки на фронтенді
                return response()->json([
                    'success' => true,
                    'message' => 'Дані підготовлено для експорту',
                    'data' => $data,
                    'filename' => 'students_export_' . now()->format('Y_m_d_H_i_s') . '.csv',
                    'total_records' => $data->count()
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'total_records' => $data->count()
            ]);

        } catch (Exception $e) {
            Log::error('TeacherStudentsController@exportStudentsData: Помилка', [
                'message' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Помилка при експорті даних: ' . $e->getMessage()
            ], 500);
        }
    }

    // Додаткові приватні методи для адмін функцій
    
    private function getTeacherAverageProgress(int $teacherId): float
    {
        $courses = Course::where('instructor_id', $teacherId)->pluck('id');
        
        if ($courses->isEmpty()) {
            return 0.0;
        }
        
        $totalProgress = 0;
        $studentCount = 0;
        
        foreach ($courses as $courseId) {
            $enrollments = CourseEnrollment::where('course_id', $courseId)
                ->where('is_active', true)
                ->get();
                
            foreach ($enrollments as $enrollment) {
                $progress = $this->getCourseProgressForStudent($enrollment->user_id, $courseId);
                $totalProgress += $progress['progress_percentage'];
                $studentCount++;
            }
        }
        
        return $studentCount > 0 ? round($totalProgress / $studentCount, 1) : 0.0;
    }}