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

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by location
        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        // Search by title or location
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('location', 'like', '%' . $search . '%');
            });
        }

        // Sort by created_at (newest first)
        $query->orderBy('created_at', 'desc');

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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'years_of_experience' => 'required|integer|min:0|max:50',
            'message' => 'sometimes|nullable|string',
            'cv' => 'required|file|mimes:pdf,doc,docx|max:5120', // 5MB max
            'availability' => 'required|in:immediate,2-weeks,1-month,2-months,negotiable',
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

            // Handle CV upload
            if ($request->hasFile('cv')) {
                $data['cv_path'] = $request->file('cv')->store('job-applications/cvs', 'public');
                // Sync to web-accessible storage
                \App\Helpers\StorageHelper::syncToPublic($data['cv_path']);
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
     * Get job statistics for public display
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_active_jobs' => Job::active()->count(),
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
                'latest_jobs_count' => Job::active()
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
            ];

            return $this->success($stats, 'Job statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }
}
