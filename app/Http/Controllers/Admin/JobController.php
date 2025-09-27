<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobController extends Controller
{
    use ResponseTrait;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get all jobs
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('view_jobs');

        $query = Job::with('author');

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

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

        // Search by title or location
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('location', 'like', '%' . $search . '%')
                  ->orWhere('department', 'like', '%' . $search . '%');
            });
        }

        $jobs = $query->orderBy('created_at', 'desc')->get()->map(function ($job) {
            return [
                'id' => $job->encoded_id,
                'title' => $job->title,
                'slug' => $job->slug,
                'location' => $job->location,
                'type' => $job->type,
                'experience_level' => $job->experience_level,
                'department' => $job->department,
                'salary_range' => $job->salary_range,
                'application_deadline' => $job->application_deadline?->format('Y-m-d'),
                'is_active' => $job->is_active,
                'is_featured' => $job->is_featured,
                'applications_count' => $job->applications_count,
                'author' => [
                    'id' => $job->author->encoded_id,
                    'name' => $job->author->name,
                    'email' => $job->author->email,
                ],
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
            ];
        });

        return $this->success($jobs, 'Jobs retrieved successfully');
    }

    /**
     * Get a specific job
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($encodedId)
    {
        $this->authorize('view_jobs');

        $job = Job::findByEncodedIdOrFail($encodedId);
        return $this->success(new JobResource($job->load('author')), 'Job retrieved successfully');
    }

    /**
     * Get job by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySlug($slug)
    {
        $this->authorize('view_jobs');

        $job = Job::with('author')->bySlug($slug)->first();

        if (!$job) {
            return $this->error('Job not found', 404);
        }

        return $this->success(new JobResource($job), 'Job retrieved successfully');
    }

    /**
     * Store a new job
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create_jobs');

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:jobs,slug',
            'location' => 'required|string|max:255',
            'type' => 'required|in:full-time,part-time,contract,internship,remote',
            'key_responsibilities' => 'required|array',
            'preferred_qualifications' => 'required|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();
            $data['created_by'] = Auth::id();

            $job = Job::create($data);
            return $this->success(new JobResource($job->load('author')), 'Job created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create job: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a job
     *
     * @param Request $request
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $encodedId)
    {
        $this->authorize('edit_jobs');

        $job = Job::findByEncodedIdOrFail($encodedId);
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:jobs,slug,' . $job->id,
            'location' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:full-time,part-time,contract,internship,remote',
            'key_responsibilities' => 'sometimes|required|array',
            'preferred_qualifications' => 'sometimes|required|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();
            $job->update($data);
            return $this->success(new JobResource($job->fresh()->load('author')), 'Job updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update job: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a job
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($encodedId)
    {
        $this->authorize('delete_jobs');

        try {
            $job = Job::findByEncodedIdOrFail($encodedId);
            
            // Check if job has applications
            if ($job->applications()->count() > 0) {
                return $this->error('Cannot delete job with existing applications', 422);
            }
            
            $job->delete();
            return $this->success(null, 'Job deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete job: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle job active status
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive($encodedId)
    {
        $this->authorize('edit_jobs');

        try {
            $job = Job::findByEncodedIdOrFail($encodedId);
            $job->update(['is_active' => !$job->is_active]);
            
            $status = $job->is_active ? 'activated' : 'deactivated';
            return $this->success([
                'job' => [
                    'id' => $job->encoded_id,
                    'title' => $job->title,
                    'is_active' => $job->is_active,
                ]
            ], "Job {$status} successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to toggle job status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle job featured status
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleFeatured($encodedId)
    {
        $this->authorize('edit_jobs');

        try {
            $job = Job::findByEncodedIdOrFail($encodedId);
            $job->update(['is_featured' => !$job->is_featured]);
            
            $status = $job->is_featured ? 'featured' : 'unfeatured';
            return $this->success([
                'job' => [
                    'id' => $job->encoded_id,
                    'title' => $job->title,
                    'is_featured' => $job->is_featured,
                ]
            ], "Job {$status} successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to toggle job featured status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get job options for form dropdowns
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOptions()
    {
        $this->authorize('view_jobs');

        try {
            $options = [
                'job_types' => [
                    'full-time' => 'Full Time',
                    'part-time' => 'Part Time',
                    'contract' => 'Contract',
                    'internship' => 'Internship',
                    'remote' => 'Remote'
                ],
                'availability_options' => [
                    'immediate' => 'Immediate',
                    '2-weeks' => '2 Weeks Notice',
                    '1-month' => '1 Month Notice',
                    '2-months' => '2 Months Notice',
                    'negotiable' => 'Negotiable'
                ],
                'validation_rules' => [
                    'title' => 'required|string|max:255',
                    'slug' => 'required|string|max:255|unique:jobs,slug',
                    'location' => 'required|string|max:255',
                    'type' => 'required|in:full-time,part-time,contract,internship,remote',
                    'key_responsibilities' => 'required|array',
                    'preferred_qualifications' => 'required|array',
                    'is_active' => 'sometimes|boolean'
                ]
            ];

            return $this->success($options, 'Job options retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get job statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        $this->authorize('view_jobs');

        try {
            $stats = [
                'total_jobs' => Job::count(),
                'active_jobs' => Job::active()->count(),
                'featured_jobs' => Job::featured()->count(),
                'jobs_by_type' => Job::selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'jobs_by_experience' => Job::selectRaw('experience_level, COUNT(*) as count')
                    ->groupBy('experience_level')
                    ->pluck('count', 'experience_level'),
                'total_applications' => \App\Models\JobApplication::count(),
                'pending_applications' => \App\Models\JobApplication::pending()->count(),
            ];

            return $this->success($stats, 'Job statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }
}
