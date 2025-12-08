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
use Illuminate\Support\Facades\Log;
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

        $projects = Project::with(['author', 'disciplines', 'sections'])->get()->map(function ($project) {
            return [
                'id' => $project->encoded_id,
                'title' => $project->title,
                'slug' => $project->slug,
                'description' => $project->description,
                'cover_photo' => $project->cover_photo ? env('APP_URL') . '/storage/' . $project->cover_photo : null,
                'is_active' => $project->is_active,
                'show_on_home' => $project->show_on_home,
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
        return $this->success(new ProjectResource($project->load(['author', 'disciplines', 'sections'])), 'Project retrieved successfully');
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
            Log::warning('Project not found by slug', ['slug' => $slug]);
            return $this->error('Resource not found', 404);
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

        $validationRules = [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:projects,slug',
            'description' => 'nullable|string',
            'cover_photo' => 'required|image|max:4096',
            'is_active' => 'sometimes|boolean',
            'show_on_home' => 'sometimes|boolean',
            'discipline_ids' => 'sometimes|array',
            'discipline_ids.*' => 'required|integer|exists:disciplines,id',
            'disciplines' => 'sometimes|array',
            'disciplines.*' => 'required|integer|exists:disciplines,id',
            'sections' => 'sometimes|array',
            'sections.*.content' => 'nullable|string',
            'sections.*.image' => 'nullable|image|max:4096',
            'sections.*.caption' => 'nullable|string|max:255',
            'sections.*.order' => 'nullable|integer',
        ];

        // Add validation for old format (backward compatibility)
        for ($i = 1; $i <= 20; $i++) {
            $validationRules["content{$i}"] = 'sometimes|nullable|string';
            $validationRules["image{$i}"] = 'sometimes|nullable|image|max:4096';
            $validationRules["caption{$i}"] = 'sometimes|nullable|string|max:255';
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();
            $data['created_by'] = Auth::id();
            
            // Sanitize: Convert string "null" to actual null
            if (isset($data['description']) && $data['description'] === 'null') {
                $data['description'] = null;
            }

            // Handle cover photo upload
            if ($request->hasFile('cover_photo')) {
                $data['cover_photo'] = $request->file('cover_photo')->store('projects/covers', 'public');
                // Sync to web-accessible storage
                StorageHelper::syncToPublic($data['cover_photo']);
            }

            // Handle sections - support old format (content1, image1, caption1) 
            $sections = $data['sections'] ?? [];
            
            // Convert old format to new format if sections array is empty
            if (empty($sections)) {
                for ($i = 1; $i <= 20; $i++) { // Support up to 20 sections
                    $contentKey = "content{$i}";
                    $imageKey = "image{$i}";
                    $captionKey = "caption{$i}";
                    
                    // Check if this section has content or image
                    if ($request->has($contentKey) || $request->hasFile($imageKey)) {
                        $sectionData = [
                            'content' => $request->input($contentKey),
                            'caption' => $request->input($captionKey),
                            'order' => $i - 1,
                        ];
                        
                        if ($request->hasFile($imageKey)) {
                            $sectionData['image'] = $request->file($imageKey);
                        }
                        
                        $sections[] = $sectionData;
                    }
                }
            }

            // Remove sections and other non-model fields from data before creating project
            unset($data['sections'], $data['discipline_ids'], $data['disciplines']);
            
            // Remove old format fields if they exist
            for ($i = 1; $i <= 20; $i++) {
                unset($data["content{$i}"], $data["image{$i}"], $data["caption{$i}"]);
            }

            $project = Project::create($data);

            // Create sections
            if (!empty($sections)) {
                foreach ($sections as $index => $sectionData) {
                    $sectionToCreate = [
                        'project_id' => $project->id,
                        'content' => $sectionData['content'] ?? null,
                        'caption' => $sectionData['caption'] ?? null,
                        'order' => $sectionData['order'] ?? $index,
                    ];

                    // Handle section image upload
                    if (isset($sectionData['image']) && $sectionData['image'] instanceof \Illuminate\Http\UploadedFile) {
                        $imagePath = $sectionData['image']->store('projects/sections', 'public');
                        StorageHelper::syncToPublic($imagePath);
                        $sectionToCreate['image'] = $imagePath;
                    }

                    $project->sections()->create($sectionToCreate);
                }
            }

            // Sync disciplines if provided (accepts both 'discipline_ids' and 'disciplines')
            $disciplineField = $request->has('discipline_ids') ? 'discipline_ids' : ($request->has('disciplines') ? 'disciplines' : null);
            if ($disciplineField) {
                $disciplineIds = array_filter($request->input($disciplineField), function($id) {
                    return is_numeric($id) && Discipline::where('id', $id)->exists();
                });
                $project->disciplines()->sync($disciplineIds);
            }

            return $this->success(new ProjectResource($project->load(['author', 'disciplines', 'sections'])), 'Project created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Project creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('Operation failed', 500);
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
        
        $validationRules = [
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:projects,slug,' . $project->id,
            'description' => 'sometimes|nullable|string',
            'cover_photo' => 'sometimes|nullable|image|max:4096',
            'is_active' => 'sometimes|boolean',
            'show_on_home' => 'sometimes|boolean',
            'discipline_ids' => 'sometimes|array',
            'discipline_ids.*' => 'required|integer|exists:disciplines,id',
            'disciplines' => 'sometimes|array',
            'disciplines.*' => 'required|integer|exists:disciplines,id',
            'sections' => 'sometimes|array',
            'sections.*.content' => 'nullable|string',
            'sections.*.image' => 'nullable|image|max:4096',
            'sections.*.caption' => 'nullable|string|max:255',
            'sections.*.order' => 'nullable|integer',
        ];

        // Add validation for old format (backward compatibility)
        for ($i = 1; $i <= 20; $i++) {
            $validationRules["content{$i}"] = 'sometimes|nullable|string';
            $validationRules["image{$i}"] = 'sometimes|nullable|image|max:4096';
            $validationRules["caption{$i}"] = 'sometimes|nullable|string|max:255';
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();
            
            // Sanitize: Convert string "null" to actual null
            if (isset($data['description']) && $data['description'] === 'null') {
                $data['description'] = null;
            }

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

            // Handle sections update - support both old format (content1, image1, caption1) and new format
            $sections = $data['sections'] ?? [];
            $hasOldFormatSections = false;
            
            // Get existing sections to preserve images
            $existingSections = $project->sections->keyBy('order')->toArray();
            
            // Convert old format to new format if sections array is empty
            if (empty($sections)) {
                for ($i = 1; $i <= 20; $i++) {
                    $contentKey = "content{$i}";
                    $imageKey = "image{$i}";
                    $captionKey = "caption{$i}";
                    
                    // Check if this section has content or image or caption
                    if ($request->has($contentKey) || $request->hasFile($imageKey) || $request->has($captionKey)) {
                        $hasOldFormatSections = true;
                        $sectionData = [
                            'content' => $request->input($contentKey),
                            'caption' => $request->input($captionKey),
                            'order' => $i - 1,
                        ];
                        
                        // If new image file is uploaded, use it
                        if ($request->hasFile($imageKey)) {
                            $sectionData['image'] = $request->file($imageKey);
                        }
                        // If image is sent as empty string, mark for deletion
                        elseif ($request->has($imageKey) && $request->input($imageKey) === '') {
                            $sectionData['delete_image'] = true;
                        }
                        // If image is sent as non-empty string (existing path), preserve it
                        elseif ($request->has($imageKey) && is_string($request->input($imageKey)) && $request->input($imageKey) !== '') {
                            $sectionData['existing_image'] = $request->input($imageKey);
                        }
                        // If image is not sent at all, preserve from existing section
                        elseif (!$request->has($imageKey) && isset($existingSections[$i - 1]['image'])) {
                            $sectionData['existing_image'] = $existingSections[$i - 1]['image'];
                        }
                        
                        $sections[] = $sectionData;
                    }
                }
            }

            // Only update sections if sections array is provided or old format fields are present
            if (!empty($sections) || $hasOldFormatSections) {
                // Collect images to delete (only delete images that are being replaced)
                $imagesToDelete = [];
                foreach ($project->sections as $section) {
                    if ($section->image) {
                        $imagesToDelete[$section->order] = $section->image;
                    }
                }
                
                // Delete all existing sections (but keep track of images)
                $project->sections()->delete();

                // Create new sections
                foreach ($sections as $index => $sectionData) {
                    $sectionToCreate = [
                        'project_id' => $project->id,
                        'content' => $sectionData['content'] ?? null,
                        'caption' => $sectionData['caption'] ?? null,
                        'order' => $sectionData['order'] ?? $index,
                    ];

                    // Handle section image upload (new file)
                    if (isset($sectionData['image']) && $sectionData['image'] instanceof \Illuminate\Http\UploadedFile) {
                        $imagePath = $sectionData['image']->store('projects/sections', 'public');
                        StorageHelper::syncToPublic($imagePath);
                        $sectionToCreate['image'] = $imagePath;
                        
                        // Delete old image for this order if it exists and is different
                        $order = $sectionData['order'] ?? $index;
                        if (isset($imagesToDelete[$order])) {
                            Storage::disk('public')->delete($imagesToDelete[$order]);
                            unset($imagesToDelete[$order]);
                        }
                    }
                    // If delete_image flag is set, don't set image (will be null) and delete old image
                    elseif (isset($sectionData['delete_image']) && $sectionData['delete_image']) {
                        $sectionToCreate['image'] = null;
                        
                        // Delete old image for this order if it exists
                        $order = $sectionData['order'] ?? $index;
                        if (isset($imagesToDelete[$order])) {
                            Storage::disk('public')->delete($imagesToDelete[$order]);
                            unset($imagesToDelete[$order]);
                        }
                    }
                    // Preserve existing image path
                    elseif (isset($sectionData['existing_image'])) {
                        $sectionToCreate['image'] = $sectionData['existing_image'];
                        
                        // Don't delete this image
                        $order = $sectionData['order'] ?? $index;
                        if (isset($imagesToDelete[$order])) {
                            unset($imagesToDelete[$order]);
                        }
                    }

                    $project->sections()->create($sectionToCreate);
                }
                
                // Delete any remaining old images that weren't preserved
                foreach ($imagesToDelete as $imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            // Remove sections and discipline fields from data before updating project
            unset($data['sections'], $data['discipline_ids'], $data['disciplines']);
            
            // Remove old format fields if they exist
            for ($i = 1; $i <= 20; $i++) {
                unset($data["content{$i}"], $data["image{$i}"], $data["caption{$i}"]);
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

            return $this->success(new ProjectResource($project->fresh()->load(['author', 'disciplines', 'sections'])), 'Project updated successfully');
        } catch (\Exception $e) {
            Log::error('Project update failed', ['error' => $e->getMessage(), 'project_id' => $encodedId, 'trace' => $e->getTraceAsString()]);
            return $this->error('Operation failed', 500);
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

            // Delete section images
            foreach ($project->sections as $section) {
                if ($section->image) {
                    Storage::disk('public')->delete($section->image);
                }
            }
            
            $project->delete();
            return $this->success(null, 'Project deleted successfully');
        } catch (\Exception $e) {
            Log::error('Project deletion failed', ['error' => $e->getMessage(), 'project_id' => $encodedId]);
            return $this->error('Operation failed', 500);
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
            Log::error('Project status toggle failed', ['error' => $e->getMessage(), 'project_id' => $encodedId]);
            return $this->error('Operation failed', 500);
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
                        // Delete cover photo if exists
                        if ($project->cover_photo) {
                            StorageHelper::deleteFromDirectory($project->cover_photo);
                        }

                        // Delete section images
                        foreach ($project->sections as $section) {
                            if ($section->image) {
                                StorageHelper::deleteFromDirectory($section->image);
                            }
                        }

                        $project->delete();
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Project bulk delete item failed', ['error' => $e->getMessage(), 'project_id' => $encodedId]);
                    $errors[] = "Failed to delete project";
                }
            }

            return $this->success([
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], "{$deletedCount} project(s) deleted successfully");
        } catch (\Exception $e) {
            Log::error('Project bulk delete failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
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
                    Log::error('Project bulk status update item failed', ['error' => $e->getMessage(), 'project_id' => $encodedId]);
                    $errors[] = "Failed to update project";
                }
            }

            $statusText = $request->status ? 'activated' : 'deactivated';
            return $this->success([
                'updated_count' => $updatedCount,
                'errors' => $errors
            ], "{$updatedCount} project(s) {$statusText} successfully");
        } catch (\Exception $e) {
            Log::error('Project bulk status update failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }
}
