<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TestQuestionResource extends JsonResource
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
            'internal_test_id' => $this->internal_test_id,
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'position' => $this->position,
            'points' => $this->points,
            'explanation' => $this->explanation,
            'is_required' => $this->is_required,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Media fields
            'media_type' => $this->media_type,
            'media_url' => $this->when($this->hasMedia(), function () {
                return $this->getMediaUrl();
            }),
            'media_original_name' => $this->media_original_name,
            'media_size' => $this->media_size,
            'has_media' => $this->hasMedia(),
            
            // Relationships
            'answers' => QuestionAnswerResource::collection($this->whenLoaded('answers')),
            
            // For students taking the test - hide correct answers and show only display data
            'answers_for_display' => $this->when(
                $request->routeIs('tests.*'), 
                function () {
                    return $this->getAnswersForDisplay()->map(function ($answer) {
                        return [
                            'id' => $answer->id,
                            'answer_text' => $answer->answer_text,
                            'position' => $answer->position,
                            'media_type' => $answer->media_type,
                            'media_url' => $answer->media_url,
                            'media_original_name' => $answer->media_original_name,
                            'has_media' => !empty($answer->media_path),
                        ];
                    });
                }
            ),
        ];
    }
}