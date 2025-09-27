<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->encoded_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'cover_photo' => $this->cover_photo_url,
            'category' => $this->category,
            'content' => $this->content,
            'mark_as_hero' => $this->mark_as_hero,
            'is_active' => $this->is_active,
            'tags' => $this->tags,
            'headings' => $this->headings,
            'faqs' => BlogFaqResource::collection($this->whenLoaded('faqs')),
            'author' => $this->whenLoaded('author', function () {
                return [
                    'name' => $this->author->name,
                    'bio' => $this->author->bio,
                    'profile_photo' => $this->author->profile_image ?  asset('storage/' . $this->author->profile_image) : null,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}