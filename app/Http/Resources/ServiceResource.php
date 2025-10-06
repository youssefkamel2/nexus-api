<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->encoded_id,
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,
            'cover_photo' => $this->cover_photo ? env('APP_URL') . '/storage/' . $this->cover_photo : null,
            'sections' => [
                [
                    'content' => $this->content1,
                    'image' => $this->image1 ? env('APP_URL') . '/storage/' . $this->image1 : null,
                ],
                [
                    'content' => $this->content2,
                    'image' => $this->image2 ? env('APP_URL') . '/storage/' . $this->image2 : null,
                ],
                [
                    'content' => $this->content3,
                    'image' => $this->image3 ? env('APP_URL') . '/storage/' . $this->image3 : null,
                ],
            ],
            'is_active' => $this->is_active,
            'author' => [
                'id' => $this->author->encoded_id,
                'name' => $this->author->name,
                'email' => $this->author->email,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
