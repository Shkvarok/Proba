<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TestResponseResource extends JsonResource
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
            'test_attempt_id' => $this->test_attempt_id,
            'test_question_id' => $this->test_question_id,
            'selected_answers' => $this->selected_answers,
            'text_answer' => $this->text_answer,
            'is_correct' => $this->is_correct,
            'points_earned' => $this->points_earned,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'test_question' => $this->whenLoaded('testQuestion'),
            
            // Computed properties
            'user_answer_text' => $this->getUserAnswerText(),
            'correct_answer_text' => $this->when(
                $this->testAttempt && $this->testAttempt->internalTest && $this->testAttempt->internalTest->show_results_immediately,
                function () {
                    return $this->getCorrectAnswerText();
                }
            ),
        ];
    }
}