<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
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
            'location' => $this->location,
            'type' => $this->type,
            'key_responsibilities' => $this->key_responsibilities,
            'preferred_qualifications' => $this->preferred_qualifications,
            'is_active' => $this->is_active,
            'applications_count' => $this->applications_count,
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
