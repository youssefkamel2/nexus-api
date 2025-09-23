<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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

        $projects = Project::with('author')->get()->map(function ($project) {
            return [
                'id' => $project->encoded_id,
                'title' => $project->title,
                'slug' => $project->slug,
                'description' => $project->description,
                'cover_photo' => $project->cover_photo ? asset('storage/' . $project->cover_photo) : null,
                'is_active' => $project->is_active,
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
        return $this->success(new ProjectResource($project->load('author')), 'Project retrieved successfully');
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

        $project = Project::with('author')->bySlug($slug)->first();

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
            'cover_photo' => 'sometimes|nullable|image|max:4096',
            'content1' => 'sometimes|nullable|string',
            'image1' => 'sometimes|nullable|image|max:4096',
            'content2' => 'sometimes|nullable|string',
            'image2' => 'sometimes|nullable|image|max:4096',
            'content3' => 'sometimes|nullable|string',
            'image3' => 'sometimes|nullable|image|max:4096',
            'is_active' => 'sometimes|boolean',
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
            }

            if ($request->hasFile('image1')) {
                $data['image1'] = $request->file('image1')->store('projects/sections', 'public');
            }

            if ($request->hasFile('image2')) {
                $data['image2'] = $request->file('image2')->store('projects/sections', 'public');
            }

            if ($request->hasFile('image3')) {
                $data['image3'] = $request->file('image3')->store('projects/sections', 'public');
            }

            $project = Project::create($data);
            return $this->success(new ProjectResource($project->load('author')), 'Project created successfully', 201);
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
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();

            // Handle file uploads
            if ($request->hasFile('cover_photo')) {
                // Delete old file if exists
                if ($project->cover_photo) {
                    Storage::disk('public')->delete($project->cover_photo);
                }
                $data['cover_photo'] = $request->file('cover_photo')->store('projects/covers', 'public');
            }

            if ($request->hasFile('image1')) {
                if ($project->image1) {
                    Storage::disk('public')->delete($project->image1);
                }
                $data['image1'] = $request->file('image1')->store('projects/sections', 'public');
            }

            if ($request->hasFile('image2')) {
                if ($project->image2) {
                    Storage::disk('public')->delete($project->image2);
                }
                $data['image2'] = $request->file('image2')->store('projects/sections', 'public');
            }

            if ($request->hasFile('image3')) {
                if ($project->image3) {
                    Storage::disk('public')->delete($project->image3);
                }
                $data['image3'] = $request->file('image3')->store('projects/sections', 'public');
            }

            $project->update($data);
            return $this->success(new ProjectResource($project->fresh()->load('author')), 'Project updated successfully');
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
}
