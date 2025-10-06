<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
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
            'job' => [
                'id' => $this->job->encoded_id,
                'title' => $this->job->title,
                'slug' => $this->job->slug,
                'location' => $this->job->location,
                'type' => $this->job->type,
            ],
            'applicant' => [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'years_of_experience' => $this->years_of_experience,
                'message' => $this->message,
                'availability' => $this->availability,
            ],
            'documents' => [
                'cv' => $this->cv_path ? env('APP_URL') . '/storage/' . $this->cv_path : null,
            ],
            'status' => $this->status,
            'status_color' => $this->status_color,
            'admin_notes' => $this->admin_notes,
            'reviewed_at' => $this->reviewed_at,
            'reviewer' => $this->reviewer ? [
                'id' => $this->reviewer->encoded_id,
                'name' => $this->reviewer->name,
                'email' => $this->reviewer->email,
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
