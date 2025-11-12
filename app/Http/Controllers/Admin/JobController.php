<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Search by title or location
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('location', 'like', '%' . $search . '%');
            });
        }

        $jobs = $query->orderBy('created_at', 'desc')->get()->map(function ($job) {
            return [
                'id' => $job->encoded_id,
                'title' => $job->title,
                'slug' => $job->slug,
                'location' => $job->location,
                'type' => $job->type,
                'key_responsibilities' => $job->key_responsibilities,
                'preferred_qualifications' => $job->preferred_qualifications,
                'is_active' => $job->is_active,
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
            Log::warning('Job not found by slug', ['slug' => $slug]);
            return $this->error('Resource not found', 404);
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
            'key_responsibilities' => 'required|string',
            'preferred_qualifications' => 'required|string',
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
            Log::error('Job creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('Operation failed', 500);
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
            'key_responsibilities' => 'sometimes|required|string',
            'preferred_qualifications' => 'sometimes|required|string',
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
            Log::error('Job update failed', ['error' => $e->getMessage(), 'job_id' => $encodedId, 'trace' => $e->getTraceAsString()]);
            return $this->error('Operation failed', 500);
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
                Log::warning('Attempt to delete job with applications', ['job_id' => $encodedId]);
                return $this->error('Operation not permitted', 422);
            }
            
            $job->delete();
            return $this->success(null, 'Job deleted successfully');
        } catch (\Exception $e) {
            Log::error('Job deletion failed', ['error' => $e->getMessage(), 'job_id' => $encodedId]);
            return $this->error('Operation failed', 500);
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
            Log::error('Job status toggle failed', ['error' => $e->getMessage(), 'job_id' => $encodedId]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Bulk delete jobs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('delete_jobs');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $deletedCount = 0;
            $errors = [];

            foreach ($request->ids as $encodedId) {
                try {
                    $job = Job::findByEncodedId($encodedId);
                    if ($job) {
                        $job->delete();
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Job bulk delete item failed', ['error' => $e->getMessage(), 'job_id' => $encodedId]);
                    $errors[] = "Failed to delete job";
                }
            }

            return $this->success([
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], "{$deletedCount} job(s) deleted successfully");
        } catch (\Exception $e) {
            Log::error('Job bulk delete failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Bulk update job status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('edit_jobs');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string',
            'status' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $updatedCount = 0;
            $errors = [];

            foreach ($request->ids as $encodedId) {
                try {
                    $job = Job::findByEncodedId($encodedId);
                    if ($job) {
                        $job->is_active = $request->status;
                        $job->save();
                        $updatedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Job bulk status update item failed', ['error' => $e->getMessage(), 'job_id' => $encodedId]);
                    $errors[] = "Failed to update job";
                }
            }

            $statusText = $request->status ? 'activated' : 'deactivated';
            return $this->success([
                'updated_count' => $updatedCount,
                'errors' => $errors
            ], "{$updatedCount} job(s) {$statusText} successfully");
        } catch (\Exception $e) {
            Log::error('Job bulk status update failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
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
            Log::error('Job options retrieval failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
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
                'inactive_jobs' => Job::where('is_active', false)->count(),
                'jobs_by_type' => Job::selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'jobs_by_location' => Job::selectRaw('location, COUNT(*) as count')
                    ->groupBy('location')
                    ->pluck('count', 'location'),
                'total_applications' => \App\Models\JobApplication::count(),
                'pending_applications' => \App\Models\JobApplication::where('status', 'pending')->count(),
            ];

            return $this->success($stats, 'Job statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Job statistics retrieval failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }
}
