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
            'description' => $this->description,
            'requirements' => $this->requirements,
            'location' => $this->location,
            'type' => $this->type,
            'experience_level' => $this->experience_level,
            'department' => $this->department,
            'salary_range' => $this->salary_range,
            'benefits' => $this->benefits,
            'application_deadline' => $this->application_deadline?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
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
