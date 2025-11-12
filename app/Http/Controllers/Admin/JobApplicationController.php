<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobApplicationResource;
use App\Models\JobApplication;
use App\Models\Job;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class JobApplicationController extends Controller
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
     * Get all job applications
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('view_job_applications');

        $query = JobApplication::with(['job', 'reviewer']);

        // Filter by job
        if ($request->has('job_id')) {
            $job = Job::findByEncodedIdOrFail($request->job_id);
            $query->where('job_id', $job->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        // Filter by reviewed status
        if ($request->has('reviewed')) {
            if ($request->reviewed === 'true') {
                $query->reviewed();
            } else {
                $query->whereNull('reviewed_at');
            }
        }

        // Search by applicant name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                  ->orWhere('last_name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $applications = $query->orderBy('created_at', 'desc')->get()->map(function ($application) {
            return [
                'id' => $application->encoded_id,
                'job' => [
                    'id' => $application->job->encoded_id,
                    'title' => $application->job->title,
                    'slug' => $application->job->slug,
                    'location' => $application->job->location,
                    'type' => $application->job->type,
                ],
                'applicant' => [
                    'name' => $application->name,
                    'email' => $application->email,
                    'phone' => $application->phone,
                    'years_of_experience' => $application->years_of_experience,
                    'availability' => $application->availability,
                    'message' => $application->message,
                ],
                'status' => $application->status,
                'status_color' => $application->status_color,
                'reviewed_at' => $application->reviewed_at,
                'reviewer' => $application->reviewer ? [
                    'name' => $application->reviewer->name,
                    'email' => $application->reviewer->email,
                ] : null,
                'created_at' => $application->created_at,
            ];
        });

        return $this->success($applications, 'Job applications retrieved successfully');
    }

    /**
     * Get a specific job application
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($encodedId)
    {
        $this->authorize('view_job_applications');

        $application = JobApplication::findByEncodedIdOrFail($encodedId);
        return $this->success(
            new JobApplicationResource($application->load(['job', 'reviewer'])), 
            'Job application retrieved successfully'
        );
    }

    /**
     * Get applications for a specific job
     *
     * @param string $jobEncodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByJob($jobEncodedId)
    {
        $this->authorize('view_job_applications');

        $job = Job::findByEncodedIdOrFail($jobEncodedId);
        $applications = JobApplication::with(['reviewer'])
            ->where('job_id', $job->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(
            JobApplicationResource::collection($applications), 
            'Job applications retrieved successfully'
        );
    }

    /**
     * Update application status
     *
     * @param Request $request
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $encodedId)
    {
        $this->authorize('manage_job_applications');

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,reviewing,shortlisted,interview,rejected,hired',
            'admin_notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $application = JobApplication::findByEncodedIdOrFail($encodedId);
            
            $application->markAsReviewed(
                Auth::id(),
                $request->status,
                $request->admin_notes
            );

            return $this->success(
                new JobApplicationResource($application->fresh()->load(['job', 'reviewer'])), 
                'Application status updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Application status update failed', ['error' => $e->getMessage(), 'application_id' => $encodedId]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Add admin notes to application
     *
     * @param Request $request
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNotes(Request $request, $encodedId)
    {
        $this->authorize('manage_job_applications');

        $validator = Validator::make($request->all(), [
            'admin_notes' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $application = JobApplication::findByEncodedIdOrFail($encodedId);
            
            $application->update([
                'admin_notes' => $request->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);

            return $this->success(
                new JobApplicationResource($application->fresh()->load(['job', 'reviewer'])), 
                'Notes added successfully'
            );
        } catch (\Exception $e) {
            Log::error('Application notes addition failed', ['error' => $e->getMessage(), 'application_id' => $encodedId]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Download application document
     *
     * @param string $encodedId
     * @param string $documentType
     * @return \Illuminate\Http\Response
     */
    public function downloadDocument($encodedId, $documentType = 'cv')
    {
        $this->authorize('view_job_applications');

        try {
            $application = JobApplication::findByEncodedIdOrFail($encodedId);

            if ($documentType !== 'cv') {
                Log::warning('Invalid document type requested', ['type' => $documentType]);
                return $this->error('Invalid request', 400);
            }

            if (!$application->cv_path) {
                Log::warning('CV not found for application', ['application_id' => $encodedId]);
                return $this->error('Resource not found', 404);
            }
            
            $filePath = $application->cv_path;
            $fileName = $application->name . '_CV.pdf';

            if (!$filePath || !Storage::disk('public')->exists($filePath)) {
                Log::warning('Document file missing', ['path' => $filePath, 'application_id' => $encodedId]);
                return $this->error('Resource not found', 404);
            }

            return Storage::disk('public')->download($filePath, $fileName);
        } catch (\Exception $e) {
            Log::error('Document download failed', ['error' => $e->getMessage(), 'application_id' => $encodedId]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Delete a job application
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($encodedId)
    {
        $this->authorize('delete_job_applications');

        try {
            $application = JobApplication::findByEncodedIdOrFail($encodedId);
            
            // Delete associated files
            if ($application->cv_path) {
                Storage::disk('public')->delete($application->cv_path);
            }
            
            // Update job applications count
            $job = $application->job;
            $application->delete();
            $job->updateApplicationsCount();
            
            return $this->success(null, 'Job application deleted successfully');
        } catch (\Exception $e) {
            Log::error('Application deletion failed', ['error' => $e->getMessage(), 'application_id' => $encodedId]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Bulk delete job applications
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('delete_job_applications');

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
            $jobsToUpdate = [];

            foreach ($request->ids as $encodedId) {
                try {
                    $application = JobApplication::findByEncodedId($encodedId);
                    if ($application) {
                        // Delete associated CV file
                        if ($application->cv_path) {
                            Storage::disk('public')->delete($application->cv_path);
                        }
                        
                        // Track job for count update
                        $jobsToUpdate[$application->job_id] = $application->job;
                        
                        $application->delete();
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Application bulk delete item failed', ['error' => $e->getMessage(), 'application_id' => $encodedId]);
                    $errors[] = "Failed to delete application";
                }
            }

            // Update applications count for affected jobs
            foreach ($jobsToUpdate as $job) {
                $job->updateApplicationsCount();
            }

            return $this->success([
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], "{$deletedCount} application(s) deleted successfully");
        } catch (\Exception $e) {
            Log::error('Application bulk delete failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Get application status options
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusOptions()
    {
        $this->authorize('view_job_applications');

        try {
            $statusOptions = [
                'statuses' => [
                    'pending' => [
                        'label' => 'Pending',
                        'color' => 'yellow',
                        'description' => 'Application received, awaiting review'
                    ],
                    'reviewing' => [
                        'label' => 'Reviewing',
                        'color' => 'blue',
                        'description' => 'Application under review'
                    ],
                    'shortlisted' => [
                        'label' => 'Shortlisted',
                        'color' => 'purple',
                        'description' => 'Candidate shortlisted for next round'
                    ],
                    'interview' => [
                        'label' => 'Interview',
                        'color' => 'indigo',
                        'description' => 'Interview scheduled or completed'
                    ],
                    'hired' => [
                        'label' => 'Hired',
                        'color' => 'green',
                        'description' => 'Candidate hired'
                    ],
                    'rejected' => [
                        'label' => 'Rejected',
                        'color' => 'red',
                        'description' => 'Application rejected'
                    ]
                ],
                'workflow' => [
                    'pending' => ['reviewing', 'shortlisted', 'rejected'],
                    'reviewing' => ['shortlisted', 'interview', 'rejected'],
                    'shortlisted' => ['interview', 'hired', 'rejected'],
                    'interview' => ['hired', 'rejected'],
                    'hired' => [],
                    'rejected' => []
                ]
            ];

            return $this->success($statusOptions, 'Application status options retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Application status options retrieval failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Get application statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        $this->authorize('view_job_applications');

        try {
            $stats = [
                'total_applications' => JobApplication::count(),
                'pending_applications' => JobApplication::pending()->count(),
                'reviewed_applications' => JobApplication::reviewed()->count(),
                'applications_by_status' => JobApplication::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'applications_this_month' => JobApplication::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'top_jobs_by_applications' => Job::withCount('applications')
                    ->orderBy('applications_count', 'desc')
                    ->take(5)
                    ->get()
                    ->map(function ($job) {
                        return [
                            'id' => $job->encoded_id,
                            'title' => $job->title,
                            'applications_count' => $job->applications_count,
                        ];
                    }),
            ];

            return $this->success($stats, 'Application statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Application statistics retrieval failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }
}
