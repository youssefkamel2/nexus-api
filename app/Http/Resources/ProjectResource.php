<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            // return the url from APP_ENV
            'cover_photo' => $this->cover_photo ? env('APP_URL') . '/storage/' . $this->cover_photo : null,
            'sections' => $this->sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'content' => $section->content,
                    'image' => $section->image ? env('APP_URL') . '/storage/' . $section->image : null,
                    'caption' => $section->caption,
                    'order' => $section->order,
                ];
            }),
            'is_active' => $this->is_active,
            'show_on_home' => $this->show_on_home,
            'disciplines' => $this->disciplines->map(function ($discipline) {
                return [
                    'id' => $discipline->id,
                    'title' => $discipline->title,
                    'slug' => $discipline->slug,
                    'description' => $discipline->description,
                    'cover_photo' => $discipline->cover_photo ? env('APP_URL') . '/storage/' . $discipline->cover_photo : null,
                    'order' => $discipline->order,
                    'is_active' => $discipline->is_active,
                ];
            }),
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
