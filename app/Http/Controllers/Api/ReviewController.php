<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Review;
use App\Models\ReviewComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Отримати всі схвалені відгуки для курсу
     */
    public function getCourseReviews($courseId)
    {
        $course = Course::findOrFail($courseId);
        
        $reviews = $course->approvedReviews()
            ->with(['user:id,name,last_name,avatar', 'comments' => function($query) {
                $query->approved()
                    ->rootLevel()
                    ->with(['user:id,name,last_name,avatar', 'replies' => function($q) {
                        $q->approved()->with('user:id,name,last_name,avatar');
                    }]);
            }])
            ->orderByDesc('created_at')
            ->paginate(10);
        
        return response()->json([
            'reviews' => $reviews,
            'average_rating' => $course->average_rating,
            'reviews_count' => $course->reviews_count
        ]);
    }
    
    /**
     * Створити новий відгук для курсу
     */
    public function storeReview(Request $request, $courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);
        
        // Перевіряємо, чи вже існує відгук від цього користувача для цього курсу
        $existingReview = Review::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
        if ($existingReview) {
            return response()->json([
                'message' => 'Ви вже залишили відгук для цього курсу',
                'review' => $existingReview
            ], 422);
        }
        
        // Чи має користувач доступ до курсу (підписка, інструктор, адмін)
        $hasAccess = $course->hasUserAccess($user->id);
        
        // Валідація
        $rules = [
            'content' => 'required|string|min:3|max:1000',
        ];
        if ($hasAccess) {
            $rules['rating'] = 'required|integer|min:1|max:5';
        } else {
            $rules['rating'] = 'nullable';
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Автоматичне схвалення відгуків для викладачів та адміністраторів
        $isAutoApproved = $user->hasAnyRole(['admin', 'super_admin', 'teacher']);
        
        // Створення відгуку
        $review = Review::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'content' => $request->content,
            'rating' => $hasAccess ? $request->rating : null,
            'is_approved' => $isAutoApproved,
        ]);
        
        return response()->json([
            'message' => $isAutoApproved 
                ? 'Ваш відгук опубліковано' 
                : 'Ваш відгук відправлено на модерацію і буде опубліковано після перевірки',
            'review' => $review,
            'is_approved' => $isAutoApproved
        ], 201);
    }
    
    /**
     * Оновити існуючий відгук
     */
    public function updateReview(Request $request, $reviewId)
    {
        $user = Auth::user();
        $review = Review::findOrFail($reviewId);
        
        // Перевіряємо, чи відгук належить поточному користувачу
        if ($review->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Ви не маєте права редагувати цей відгук'
            ], 403);
        }
        
        // Валідація даних
        $validator = Validator::make($request->all(), [
            'content' => 'sometimes|required|string|min:3|max:1000',
            'rating' => 'sometimes|required|integer|min:1|max:5',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Оновлення відгуку
        if ($request->has('content')) {
            $review->content = $request->content;
        }
        
        if ($request->has('rating')) {
            $review->rating = $request->rating;
        }
        
        // Скидання схвалення при редагуванні (крім адміністраторів)
        if (!$user->isAdmin()) {
            $review->is_approved = false;
        }
        
        $review->save();
        
        return response()->json([
            'message' => $user->isAdmin() 
                ? 'Відгук успішно оновлено' 
                : 'Ваш відгук відправлено на повторну модерацію',
            'review' => $review
        ]);
    }
    
    /**
     * Видалити відгук
     */
    public function deleteReview($reviewId)
    {
        $user = Auth::user();
        $review = Review::findOrFail($reviewId);
        
        // Перевіряємо, чи відгук належить поточному користувачу або користувач є адміністратором
        if ($review->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Ви не маєте права видалити цей відгук'
            ], 403);
        }
        
        $review->delete();
        
        return response()->json([
            'message' => 'Відгук успішно видалено'
        ]);
    }
    
    /**
     * Додати коментар до відгуку
     */
    public function storeComment(Request $request, $reviewId)
    {
        $user = Auth::user();
        $review = Review::findOrFail($reviewId);
        
        // Валідація даних
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:3|max:500',
            'parent_id' => 'nullable|exists:review_comments,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Перевірка parent_id, якщо вказано
        if ($request->has('parent_id') && $request->parent_id) {
            $parentComment = ReviewComment::findOrFail($request->parent_id);
            
            // Перевіряємо, чи належить коментар до того ж відгуку
            if ($parentComment->review_id !== $review->id) {
                return response()->json([
                    'message' => 'Батьківський коментар належить до іншого відгуку'
                ], 422);
            }
        }
        
        // Автоматичне схвалення коментарів для адміністраторів, викладачів та автора курсу
        $isAutoApproved = $user->isAdmin() || 
                          $user->hasRole('teacher') || 
                          $review->course->instructor_id === $user->id;
        
        // Створення коментаря
        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => $request->content,
            'parent_id' => $request->parent_id,
            'is_approved' => $isAutoApproved,
        ]);
        
        if ($isAutoApproved) {
            // Завантажуємо користувача для відповіді
            $comment->load('user:id,name,last_name,avatar');
        }
        
        return response()->json([
            'message' => $isAutoApproved 
                ? 'Ваш коментар опубліковано' 
                : 'Ваш коментар відправлено на модерацію',
            'comment' => $comment,
            'is_approved' => $isAutoApproved
        ], 201);
    }
    
    /**
     * Оновити коментар
     */
    public function updateComment(Request $request, $commentId)
    {
        $user = Auth::user();
        $comment = ReviewComment::findOrFail($commentId);
        
        // Перевіряємо, чи коментар належить поточному користувачу або користувач є адміністратором
        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Ви не маєте права редагувати цей коментар'
            ], 403);
        }
        
        // Валідація даних
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:3|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Помилка валідації',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Оновлення коментаря
        $comment->content = $request->content;
        
        // Скидання схвалення при редагуванні (крім адміністраторів)
        if (!$user->isAdmin()) {
            $comment->is_approved = false;
        }
        
        $comment->save();
        
        return response()->json([
            'message' => $user->isAdmin() 
                ? 'Коментар успішно оновлено' 
                : 'Ваш коментар відправлено на повторну модерацію',
            'comment' => $comment
        ]);
    }
    
    /**
     * Видалити коментар
     */
    public function deleteComment($commentId)
    {
        $user = Auth::user();
        $comment = ReviewComment::findOrFail($commentId);
        
        // Перевіряємо, чи коментар належить поточному користувачу або користувач є адміністратором
        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Ви не маєте права видалити цей коментар'
            ], 403);
        }
        
        $comment->delete();
        
        return response()->json([
            'message' => 'Коментар успішно видалено'
        ]);
    }
    
    /**
     * Методи для модерації відгуків і коментарів (тільки для адміністраторів)
     */
    
    /**
     * Отримати відгуки, які очікують модерації
     */
    public function getPendingReviews()
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Доступ заборонено'
            ], 403);
        }
        
        $pendingReviews = Review::where('is_approved', false)
            ->with(['user:id,name,last_name,avatar', 'course:id,title'])
            ->orderByDesc('created_at')
            ->paginate(15);
            
        return response()->json([
            'pending_reviews' => $pendingReviews
        ]);
    }
    
    /**
     * Отримати коментарі, які очікують модерації
     */
    public function getPendingComments()
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Доступ заборонено'
            ], 403);
        }
        
        $pendingComments = ReviewComment::where('is_approved', false)
            ->with(['user:id,name,last_name,avatar', 'review.course:id,title'])
            ->orderByDesc('created_at')
            ->paginate(15);
            
        return response()->json([
            'pending_comments' => $pendingComments
        ]);
    }
    
    /**
     * Схвалити відгук
     */
    public function approveReview($reviewId)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Доступ заборонено'
            ], 403);
        }
        
        $review = Review::findOrFail($reviewId);
        $review->is_approved = true;
        $review->save();
        
        return response()->json([
            'message' => 'Відгук схвалено',
            'review' => $review
        ]);
    }
    
    /**
     * Схвалити коментар
     */
    public function approveComment($commentId)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Доступ заборонено'
            ], 403);
        }
        
        $comment = ReviewComment::findOrFail($commentId);
        $comment->is_approved = true;
        $comment->save();
        
        return response()->json([
            'message' => 'Коментар схвалено',
            'comment' => $comment
        ]);
    }
    
    /**
     * Відхилити відгук
     */
    public function rejectReview($reviewId)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Доступ заборонено'
            ], 403);
        }
        
        $review = Review::findOrFail($reviewId);
        $review->delete();
        
        return response()->json([
            'message' => 'Відгук відхилено'
        ]);
    }
    
    /**
     * Відхилити коментар
     */
    public function rejectComment($commentId)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Доступ заборонено'
            ], 403);
        }
        
        $comment = ReviewComment::findOrFail($commentId);
        $comment->delete();
        
        return response()->json([
            'message' => 'Коментар відхилено'
        ]);
    }
}