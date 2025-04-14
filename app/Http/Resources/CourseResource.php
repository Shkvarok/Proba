<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'category_id' => $this->category_id,
            'instructor' => new UserResource($this->whenLoaded('instructor')),
            'instructor_id' => $this->instructor_id,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'discount_expires_at' => $this->discount_expires_at,
            'is_on_discount' => $this->isOnDiscount(),
            'current_price' => $this->getCurrentPrice(),
            'level' => new LevelResource($this->whenLoaded('level')),
            'level_id' => $this->level_id,
            'language' => $this->language,
            'cover_image' => $this->cover_image ? Storage::url($this->cover_image) : null,
            'promo_video_url' => $this->promo_video_url,
            'requirements' => $this->requirements,
            'what_you_learn' => $this->what_you_learn,
            'is_published' => $this->is_published,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}