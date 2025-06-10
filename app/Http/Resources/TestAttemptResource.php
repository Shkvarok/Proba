<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


class TestAttemptResource extends JsonResource
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
            'user_id' => $this->user_id,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'score' => $this->score,
            'max_score' => $this->max_score,
            'percentage' => $this->percentage,
            'is_passed' => $this->is_passed,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'internal_test' => $this->whenLoaded('internalTest'),
            'responses' => TestResponseResource::collection($this->whenLoaded('responses')),
            
            // Computed properties
            'duration_minutes' => $this->when($this->completed_at, function () {
                return $this->started_at->diffInMinutes($this->completed_at);
            }),
            'remaining_time_minutes' => $this->when(
                $this->status === 'in_progress',
                function () {
                    return $this->getRemainingTimeMinutes();
                }
            ),
            'progress' => $this->when(
                $this->status === 'in_progress',
                function () {
                    return $this->getProgress();
                }
            ),
        ];
    }
}