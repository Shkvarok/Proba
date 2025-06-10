<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InternalTestResource extends JsonResource
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
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'description' => $this->description,
            'passing_score' => $this->passing_score,
            'time_limit_minutes' => $this->time_limit_minutes,
            'status' => $this->status,
            'randomize_questions' => $this->randomize_questions,
            'questions_to_show' => $this->questions_to_show,
            'max_attempts' => $this->max_attempts,
            'show_results_immediately' => $this->show_results_immediately,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'lesson' => $this->whenLoaded('lesson', function () {
                return [
                    'id' => $this->lesson->id,
                    'title' => $this->lesson->title,
                    'module' => $this->lesson->module ? [
                        'id' => $this->lesson->module->id,
                        'title' => $this->lesson->module->title,
                        'course' => $this->lesson->module->course ? [
                            'id' => $this->lesson->module->course->id,
                            'title' => $this->lesson->module->course->title,
                        ] : null,
                    ] : null,
                ];
            }),
            'questions' => TestQuestionResource::collection($this->whenLoaded('questions')),
            
            // Computed properties
            'total_questions' => $this->when($this->relationLoaded('questions'), function () {
                return $this->questions->count();
            }),
            'max_score' => $this->when($this->relationLoaded('questions'), function () {
                return $this->getMaxScore();
            }),
            'is_active' => $this->isActive(),
            'questions_with_media' => $this->when($this->relationLoaded('questions'), function () {
                return $this->questions->where('media_path', '!=', null)->count();
            }),
            'total_media_size' => $this->when($this->relationLoaded('questions'), function () {
                $questionsSize = $this->questions->sum('media_size');
                $answersSize = $this->questions->flatMap->answers->sum('media_size');
                return $questionsSize + $answersSize;
            }),
        ];
    }
}