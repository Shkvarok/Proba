<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LessonProgressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'lesson_id' => $this->lesson_id,
            'is_completed' => $this->is_completed,
            'progress_percentage' => $this->progress_percentage,
            'time_spent' => $this->time_spent,
            'formatted_time_spent' => $this->formatted_time_spent,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'last_accessed_at' => $this->last_accessed_at,
            'additional_data' => $this->additional_data,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Включаємо інформацію про урок, якщо завантажено
            'lesson' => $this->whenLoaded('lesson', function() {
                return [
                    'id' => $this->lesson->id,
                    'title' => $this->lesson->title,
                    'description' => $this->lesson->description,
                    'type' => $this->lesson->type,
                    'position' => $this->lesson->position,
                    'status' => $this->lesson->status,
                    'module_title' => $this->lesson->module ? $this->lesson->module->title : null,
                    'course_title' => $this->lesson->module && $this->lesson->module->course ? $this->lesson->module->course->title : null,
                ];
            }),
            
            // Включаємо інформацію про користувача, якщо завантажено
            'user' => $this->whenLoaded('user', function() {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'last_name' => $this->user->last_name,
                    'full_name' => trim($this->user->name . ' ' . $this->user->last_name),
                    'email' => $this->user->email,
                ];
            }),
            
            // Додаткові поля для аналітики
            'progress_status' => $this->getProgressStatus(),
            'completion_time' => $this->getCompletionTime(),
        ];
    }
    
    /**
     * Отримати статус прогресу
     */
    private function getProgressStatus(): string
    {
        if ($this->is_completed) {
            return 'completed';
        }
        
        if ($this->started_at) {
            if ($this->progress_percentage > 0) {
                return 'in_progress';
            }
            return 'started';
        }
        
        return 'not_started';
    }
    
    /**
     * Отримати час завершення (якщо завершено)
     */
    private function getCompletionTime(): ?int
    {
        if ($this->is_completed && $this->started_at && $this->completed_at) {
            return $this->completed_at->diffInSeconds($this->started_at);
        }
        
        return null;
    }
}