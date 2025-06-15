<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'instructor_id' => $this->instructor_id,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'discount_expires_at' => $this->discount_expires_at,
            'is_on_discount' => $this->isOnDiscount(),
            'current_price' => $this->getCurrentPrice(),
            'level_id' => $this->level_id,
            'language' => $this->language,
            'cover_image' => $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null,
            'promo_video_url' => $this->promo_video_url,
            'requirements' => $this->requirements,
            'what_you_learn' => $this->what_you_learn,
            'is_published' => $this->is_published,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        // Додаємо статистику
        $data['modules_count'] = $this->getModulesCount();
        $data['lessons_count'] = $this->getLessonsCount();
        $data['enrollments_count'] = $this->getEnrollmentsCount();
        $data['reviews_count'] = $this->getReviewsCount();
        $data['average_rating'] = $this->getAverageRating();

        // Додаємо відносини якщо завантажені
        if ($this->relationLoaded('category')) {
            $data['category'] = [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug ?? null
            ];
        }

        if ($this->relationLoaded('instructor')) {
            $data['instructor'] = [
                'id' => $this->instructor->id,
                'name' => $this->instructor->name,
                'email' => $this->instructor->email
            ];
        }

        if ($this->relationLoaded('level')) {
            $data['level'] = [
                'id' => $this->level->id,
                'name' => $this->level->name
            ];
        }

        // Додаємо модулі якщо завантажені
        if ($this->relationLoaded('modules')) {
            $data['modules'] = $this->modules->map(function($module) {
                $moduleData = [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'position' => $module->position,
                ];

                // Додаємо уроки якщо завантажені
                if ($module->relationLoaded('lessons')) {
                    $moduleData['lessons'] = $module->lessons->map(function($lesson) {
                        return [
                            'id' => $lesson->id,
                            'title' => $lesson->title,
                            'description' => $lesson->description,
                            'type' => $lesson->type,
                            'position' => $lesson->position,
                            'status' => $lesson->status
                        ];
                    });
                }

                return $moduleData;
            });
        }

        return $data;
    }

    /**
     * Безпечно отримати кількість модулів
     */
    private function getModulesCount(): int
    {
        try {
            if ($this->relationLoaded('modules')) {
                return $this->modules->count();
            }
            return $this->modules()->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Безпечно отримати кількість уроків
     */
    private function getLessonsCount(): int
    {
        try {
            if ($this->relationLoaded('modules')) {
                return $this->modules->sum(function($module) {
                    return $module->relationLoaded('lessons') 
                        ? $module->lessons->count() 
                        : $module->lessons()->count();
                });
            }
            return \App\Models\Lesson::whereHas('module', function($query) {
                $query->where('course_id', $this->id);
            })->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Безпечно отримати кількість підписок
     */
    private function getEnrollmentsCount(): int
    {
        try {
            if ($this->relationLoaded('enrollments')) {
                return $this->enrollments->where('is_active', true)->count();
            }
            return $this->enrollments()->where('is_active', true)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Безпечно отримати кількість відгуків
     */
    private function getReviewsCount(): int
    {
        try {
            if ($this->relationLoaded('reviews')) {
                return $this->reviews->where('status', 'approved')->count();
            }
            return $this->approvedReviews()->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Безпечно отримати середній рейтинг
     */
    private function getAverageRating(): float
    {
        try {
            if ($this->relationLoaded('reviews')) {
                $approvedReviews = $this->reviews->where('status', 'approved');
                return $approvedReviews->count() > 0 
                    ? round($approvedReviews->avg('rating'), 1) 
                    : 0.0;
            }
            return round($this->approvedReviews()->avg('rating') ?: 0, 1);
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}