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
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'instructor_id' => $this->instructor_id,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'discount_expires_at' => $this->discount_expires_at,
            'is_on_discount' => $this->is_on_discount,
            'current_price' => $this->current_price,
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
            
            // Relationships
            'category' => $this->whenLoaded('category'),
            'instructor' => $this->whenLoaded('instructor'),
            'level' => $this->whenLoaded('level'),
            'modules' => $this->whenLoaded('modules')
        ];
    }
}