<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Models\JobApplication;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class JobController extends Controller
{
    use ResponseTrait;

    /**
     * Get all active jobs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Job::with('author')->active();

        // Filter by featured
        if ($request->has('featured') && $request->featured === 'true') {
            $query->featured();
        }

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Filter by experience level
        if ($request->has('experience_level')) {
            $query->byExperienceLevel($request->experience_level);
        }

        // Filter by location
        if ($request->has('location')) {
            $query->byLocation($request->location);
        }

        // Search by title, location, or department
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('location', 'like', '%' . $search . '%')
                  ->orWhere('department', 'like', '%' . $search . '%');
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if ($sortBy === 'featured') {
            $query->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $jobs = $query->get();
        return $this->success(JobResource::collection($jobs), 'Jobs retrieved successfully');
    }

    /**
     * Get job by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySlug($slug)
    {
        $job = Job::with('author')->active()->bySlug($slug)->first();

        if (!$job) {
            return $this->error('Job not found', 404);
        }

        return $this->success(new JobResource($job), 'Job retrieved successfully');
    }

    /**
     * Get featured jobs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function featured()
    {
        $jobs = Job::with('author')->active()->featured()->orderBy('created_at', 'desc')->get();
        return $this->success(JobResource::collection($jobs), 'Featured jobs retrieved successfully');
    }

    /**
     * Apply for a job
     *
     * @param Request $request
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function apply(Request $request, $slug)
    {
        $job = Job::active()->bySlug($slug)->first();

        if (!$job) {
            return $this->error('Job not found', 404);
        }

        // Check if application deadline has passed
        if ($job->application_deadline && $job->application_deadline->isPast()) {
            return $this->error('Application deadline has passed', 422);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'sometimes|nullable|string',
            'linkedin_profile' => 'sometimes|nullable|url',
            'portfolio_website' => 'sometimes|nullable|url',
            'cover_letter' => 'required|string',
            'resume' => 'required|file|mimes:pdf,doc,docx|max:5120', // 5MB max
            'portfolio' => 'sometimes|nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            'additional_documents.*' => 'sometimes|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'years_of_experience' => 'required|integer|min:0|max:50',
            'current_position' => 'sometimes|nullable|string|max:255',
            'current_company' => 'sometimes|nullable|string|max:255',
            'expected_salary' => 'sometimes|nullable|numeric|min:0',
            'availability' => 'required|in:immediate,2-weeks,1-month,2-months,negotiable',
            'willing_to_relocate' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if user already applied for this job
        $existingApplication = JobApplication::where('job_id', $job->id)
            ->where('email', $request->email)
            ->first();

        if ($existingApplication) {
            return $this->error('You have already applied for this job', 422);
        }

        try {
            $data = $validator->validated();
            $data['job_id'] = $job->id;

            // Handle file uploads
            if ($request->hasFile('resume')) {
                $data['resume_path'] = $request->file('resume')->store('job-applications/resumes', 'public');
            }

            if ($request->hasFile('portfolio')) {
                $data['portfolio_path'] = $request->file('portfolio')->store('job-applications/portfolios', 'public');
            }

            // Handle additional documents
            if ($request->hasFile('additional_documents')) {
                $additionalDocs = [];
                foreach ($request->file('additional_documents') as $file) {
                    $additionalDocs[] = $file->store('job-applications/additional', 'public');
                }
                $data['additional_documents'] = $additionalDocs;
            }

            $application = JobApplication::create($data);
            
            // Update job applications count
            $job->updateApplicationsCount();

            return $this->success([
                'application_id' => $application->encoded_id,
                'message' => 'Application submitted successfully',
                'job' => [
                    'title' => $job->title,
                    'location' => $job->location,
                ]
            ], 'Application submitted successfully', 201);

        } catch (\Exception $e) {
            return $this->error('Failed to submit application: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get job types for filtering
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJobTypes()
    {
        $types = [
            'full-time' => 'Full Time',
            'part-time' => 'Part Time',
            'contract' => 'Contract',
            'internship' => 'Internship',
            'remote' => 'Remote'
        ];

        return $this->success($types, 'Job types retrieved successfully');
    }

    /**
     * Get experience levels for filtering
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExperienceLevels()
    {
        $levels = [
            'entry' => 'Entry Level',
            'mid' => 'Mid Level',
            'senior' => 'Senior Level',
            'executive' => 'Executive Level'
        ];

        return $this->success($levels, 'Experience levels retrieved successfully');
    }

    /**
     * Get job locations for filtering
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLocations()
    {
        $locations = Job::active()
            ->select('location')
            ->distinct()
            ->orderBy('location')
            ->pluck('location')
            ->toArray();

        return $this->success($locations, 'Job locations retrieved successfully');
    }

    /**
     * Get availability options for job application form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailabilityOptions()
    {
        $options = [
            'immediate' => 'Immediate',
            '2-weeks' => '2 Weeks Notice',
            '1-month' => '1 Month Notice',
            '2-months' => '2 Months Notice',
            'negotiable' => 'Negotiable'
        ];

        return $this->success($options, 'Availability options retrieved successfully');
    }

    /**
     * Get job statistics for public display
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_active_jobs' => Job::active()->count(),
                'featured_jobs' => Job::active()->featured()->count(),
                'jobs_by_type' => Job::active()
                    ->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'jobs_by_location' => Job::active()
                    ->selectRaw('location, COUNT(*) as count')
                    ->groupBy('location')
                    ->orderBy('count', 'desc')
                    ->take(5)
                    ->pluck('count', 'location'),
            ];

            return $this->success($stats, 'Job statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }
}
