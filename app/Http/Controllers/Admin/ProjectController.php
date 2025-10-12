<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Discipline;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Helpers\StorageHelper;


class ProjectController extends Controller
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
     * Get all projects
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $this->authorize('view_projects');

        $projects = Project::with(['author', 'disciplines'])->get()->map(function ($project) {
            return [
                'id' => $project->encoded_id,
                'title' => $project->title,
                'slug' => $project->slug,
                'description' => $project->description,
                'cover_photo' => $project->cover_photo ? env('APP_URL') . '/storage/' . $project->cover_photo : null,
                'is_active' => $project->is_active,
                'disciplines' => $project->disciplines->map(function ($discipline) {
                    return [
                        'id' => $discipline->id,
                        'title' => $discipline->title,
                    ];
                }),
                'author' => [
                    'id' => $project->author->encoded_id,
                    'name' => $project->author->name,
                    'email' => $project->author->email,
                ],
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ];
        });

        return $this->success($projects, 'Projects retrieved successfully');
    }

    /**
     * Get a specific project
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($encodedId)
    {
        $this->authorize('view_projects');

        $project = Project::findByEncodedIdOrFail($encodedId);
        return $this->success(new ProjectResource($project->load(['author', 'disciplines'])), 'Project retrieved successfully');
    }

    /**
     * Get project by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySlug($slug)
    {
        $this->authorize('view_projects');

        $project = Project::with(['author', 'disciplines'])->bySlug($slug)->first();

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        return $this->success(new ProjectResource($project), 'Project retrieved successfully');
    }

    /**
     * Store a new project
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create_projects');

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:projects,slug',
            'description' => 'required|string',
            'cover_photo' => 'required|image|max:4096',
            'content1' => 'sometimes|nullable|string',
            'image1' => 'sometimes|nullable|image|max:4096',
            'content2' => 'sometimes|nullable|string',
            'image2' => 'sometimes|nullable|image|max:4096',
            'content3' => 'sometimes|nullable|string',
            'image3' => 'sometimes|nullable|image|max:4096',
            'is_active' => 'sometimes|boolean',
            'discipline_ids' => 'sometimes|array',
            'discipline_ids.*' => 'required|integer|exists:disciplines,id',
            'disciplines' => 'sometimes|array',
            'disciplines.*' => 'required|integer|exists:disciplines,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();
            $data['created_by'] = Auth::id();

            // Handle file uploads
            if ($request->hasFile('cover_photo')) {
                $data['cover_photo'] = $request->file('cover_photo')->store('projects/covers', 'public');
                // Sync to web-accessible storage
                StorageHelper::syncToPublic($data['cover_photo']);
            }

            if ($request->hasFile('image1')) {
                $data['image1'] = $request->file('image1')->store('projects/sections', 'public');
                // Sync to web-accessible storage
                StorageHelper::syncToPublic($data['image1']);
            }

            if ($request->hasFile('image2')) {
                $data['image2'] = $request->file('image2')->store('projects/sections', 'public');
                // Sync to web-accessible storage
                StorageHelper::syncToPublic($data['image2']);
            }

            if ($request->hasFile('image3')) {
                $data['image3'] = $request->file('image3')->store('projects/sections', 'public');
                // Sync to web-accessible storage
                StorageHelper::syncToPublic($data['image3']);
            }

            $project = Project::create($data);

            // Sync disciplines if provided (accepts both 'discipline_ids' and 'disciplines')
            $disciplineField = $request->has('discipline_ids') ? 'discipline_ids' : ($request->has('disciplines') ? 'disciplines' : null);
            if ($disciplineField) {
                $disciplineIds = array_filter($request->input($disciplineField), function($id) {
                    return is_numeric($id) && Discipline::where('id', $id)->exists();
                });
                $project->disciplines()->sync($disciplineIds);
            }

            return $this->success(new ProjectResource($project->load(['author', 'disciplines'])), 'Project created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create project: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a project
     *
     * @param Request $request
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $encodedId)
    {
        $this->authorize('edit_projects');

        $project = Project::findByEncodedIdOrFail($encodedId);
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:projects,slug,' . $project->id,
            'description' => 'sometimes|required|string',
            'cover_photo' => 'sometimes|nullable|image|max:4096',
            'content1' => 'sometimes|nullable|string',
            'image1' => 'sometimes|nullable|image|max:4096',
            'content2' => 'sometimes|nullable|string',
            'image2' => 'sometimes|nullable|image|max:4096',
            'content3' => 'sometimes|nullable|string',
            'image3' => 'sometimes|nullable|image|max:4096',
            'is_active' => 'sometimes|boolean',
            'discipline_ids' => 'sometimes|array',
            'discipline_ids.*' => 'required|integer|exists:disciplines,id',
            'disciplines' => 'sometimes|array',
            'disciplines.*' => 'required|integer|exists:disciplines,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();

            // Handle cover photo upload
            if ($request->hasFile('cover_photo')) {
                // Delete old file if exists
                if ($project->cover_photo) {
                    Storage::disk('public')->delete($project->cover_photo);
                }
                $data['cover_photo'] = $request->file('cover_photo')->store('projects/covers', 'public');
                // Sync to web-accessible storage
                StorageHelper::syncToPublic($data['cover_photo']);
            } elseif ($request->has('cover_photo') && $request->input('cover_photo') === null) {
                // If cover_photo field is explicitly set to null, remove the existing image
                if ($project->cover_photo) {
                    Storage::disk('public')->delete($project->cover_photo);
                }
                $data['cover_photo'] = null;
            }

            // Handle section images - check if they should be removed or updated
            for ($i = 1; $i <= 3; $i++) {
                $imageKey = "image{$i}";
                
                if ($request->hasFile($imageKey)) {
                    // Delete old image if exists
                    if ($project->$imageKey) {
                        Storage::disk('public')->delete($project->$imageKey);
                    }
                    // Upload new image
                    $data[$imageKey] = $request->file($imageKey)->store('projects/sections', 'public');
                    // Sync to web-accessible storage
                    StorageHelper::syncToPublic($data[$imageKey]);
                } elseif ($request->has($imageKey) && $request->input($imageKey) === null) {
                    // If image field is explicitly set to null, remove the existing image
                    if ($project->$imageKey) {
                        Storage::disk('public')->delete($project->$imageKey);
                    }
                    $data[$imageKey] = null;
                }
            }

            $project->update($data);

            // Sync disciplines if provided (accepts both 'discipline_ids' and 'disciplines')
            $disciplineField = $request->has('discipline_ids') ? 'discipline_ids' : ($request->has('disciplines') ? 'disciplines' : null);
            if ($disciplineField) {
                $disciplineIds = array_filter($request->input($disciplineField), function($id) {
                    return is_numeric($id) && Discipline::where('id', $id)->exists();
                });
                $project->disciplines()->sync($disciplineIds);
            }

            return $this->success(new ProjectResource($project->fresh()->load(['author', 'disciplines'])), 'Project updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update project: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a project
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($encodedId)
    {
        $this->authorize('delete_projects');

        try {
            $project = Project::findByEncodedIdOrFail($encodedId);
            
            // Delete associated files
            if ($project->cover_photo) {
                Storage::disk('public')->delete($project->cover_photo);
            }
            if ($project->image1) {
                Storage::disk('public')->delete($project->image1);
            }
            if ($project->image2) {
                Storage::disk('public')->delete($project->image2);
            }
            if ($project->image3) {
                Storage::disk('public')->delete($project->image3);
            }
            
            $project->delete();
            return $this->success(null, 'Project deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete project: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle project active status
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive($encodedId)
    {
        $this->authorize('edit_projects');

        try {
            $project = Project::findByEncodedIdOrFail($encodedId);
            $project->update(['is_active' => !$project->is_active]);
            
            $status = $project->is_active ? 'activated' : 'deactivated';
            return $this->success([
                'project' => [
                    'id' => $project->encoded_id,
                    'title' => $project->title,
                    'is_active' => $project->is_active,
                ]
            ], "Project {$status} successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to toggle project status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk delete projects
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('delete_projects');

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
                    $project = Project::findByEncodedId($encodedId);
                    if ($project) {
                        // Delete images if exist
                        if ($project->cover_photo) {
                            StorageHelper::deleteFromDirectory($project->cover_photo);
                        }
                        if ($project->image1) {
                            StorageHelper::deleteFromDirectory($project->image1);
                        }
                        if ($project->image2) {
                            StorageHelper::deleteFromDirectory($project->image2);
                        }
                        if ($project->image3) {
                            StorageHelper::deleteFromDirectory($project->image3);
                        }
                        $project->delete();
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to delete project {$encodedId}: " . $e->getMessage();
                }
            }

            return $this->success([
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], "{$deletedCount} project(s) deleted successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to delete projects: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk update project status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('edit_projects');

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
                    $project = Project::findByEncodedId($encodedId);
                    if ($project) {
                        $project->is_active = $request->status;
                        $project->save();
                        $updatedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to update project {$encodedId}: " . $e->getMessage();
                }
            }

            $statusText = $request->status ? 'activated' : 'deactivated';
            return $this->success([
                'updated_count' => $updatedCount,
                'errors' => $errors
            ], "{$updatedCount} project(s) {$statusText} successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to update project status: ' . $e->getMessage(), 500);
        }
    }
}
