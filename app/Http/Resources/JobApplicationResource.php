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
                'full_name' => $this->full_name,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'linkedin_profile' => $this->linkedin_profile,
                'portfolio_website' => $this->portfolio_website,
            ],
            'application_details' => [
                'cover_letter' => $this->cover_letter,
                'years_of_experience' => $this->years_of_experience,
                'current_position' => $this->current_position,
                'current_company' => $this->current_company,
                'expected_salary' => $this->expected_salary,
                'availability' => $this->availability,
                'willing_to_relocate' => $this->willing_to_relocate,
            ],
            'documents' => [
                'resume' => $this->resume_path ? asset('storage/' . $this->resume_path) : null,
                'portfolio' => $this->portfolio_path ? asset('storage/' . $this->portfolio_path) : null,
                'additional_documents' => $this->additional_documents ? 
                    collect($this->additional_documents)->map(function ($doc) {
                        return asset('storage/' . $doc);
                    })->toArray() : [],
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
