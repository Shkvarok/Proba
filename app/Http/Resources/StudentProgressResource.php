<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentProgressResource extends JsonResource
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
            'name' => $this->name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->name . ' ' . $this->last_name),
            'email' => $this->email,
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'enrolled_at' => $this->whenPivotLoaded('course_enrollments', function () {
                return $this->pivot->enrolled_at;
            }),
            'enrollment_status' => $this->whenPivotLoaded('course_enrollments', function () {
                return $this->pivot->is_active ? 'active' : 'inactive';
            }),
            'expires_at' => $this->whenPivotLoaded('course_enrollments', function () {
                return $this->pivot->expires_at;
            }),
            
            // Прогрес курсу (якщо завантажений)
            'course_progress' => $this->when(isset($this->course_progress), $this->course_progress),
            
            // Остання активність
            'last_activity' => $this->when(isset($this->last_activity), $this->last_activity),
            
            // Загальний час навчання
            'total_time_spent' => $this->when(isset($this->total_time_spent), $this->total_time_spent),
            
            // Статус студента
            'status' => $this->when(isset($this->status), $this->status),
            
            // Детальний прогрес (для детального перегляду)
            'detailed_progress' => $this->when(isset($this->detailed_progress), $this->detailed_progress),
            
            'created_at' => $this->created_at,
        ];
    }
}