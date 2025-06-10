<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QuestionAnswerResource extends JsonResource
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
            'test_question_id' => $this->test_question_id,
            'answer_text' => $this->answer_text,
            'position' => $this->position,
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
            
            // Show correct answer only for teachers/admins managing tests
            'is_correct' => $this->when(
                $request->routeIs('internal-tests.*'), 
                $this->is_correct
            ),
        ];
    }
}