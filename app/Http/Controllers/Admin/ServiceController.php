<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\Discipline;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Helpers\StorageHelper;


class ServiceController extends Controller
{
    use ResponseTrait;

    /**
     * Create a new ServiceController instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get all services
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $this->authorize('view_services');

        $services = Service::with(['author', 'disciplines', 'sections'])->get()->map(function ($service) {
            return [
                'id' => $service->encoded_id,
                'title' => $service->title,
                'description' => $service->description,
                'slug' => $service->slug,
                'cover_photo' => $service->cover_photo ? env('APP_URL') . '/storage/' . $service->cover_photo : null,
                'is_active' => $service->is_active,
                'disciplines' => $service->disciplines->map(function ($discipline) {
                    return [
                        'id' => $discipline->id,
                        'title' => $discipline->title,
                    ];
                }),
                'author' => [
                    'id' => $service->author->encoded_id,
                    'name' => $service->author->name,
                    'email' => $service->author->email,
                ],
                'created_at' => $service->created_at,
                'updated_at' => $service->updated_at,
            ];
        });

        return $this->success($services, 'Services retrieved successfully');
    }

    /**
     * Get a specific service
     *
     * @param Service $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($encodedId)
    {
        $this->authorize('view_services');

        $service = Service::findByEncodedIdOrFail($encodedId);
        return $this->success(new ServiceResource($service->load(['author', 'disciplines', 'sections'])), 'Service retrieved successfully');
    }

    /**
     * Get service by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySlug($slug)
    {
        $this->authorize('view_services');

        $service = Service::with(['author', 'disciplines', 'sections'])->bySlug($slug)->first();

        if (!$service) {
            Log::warning('Service not found by slug', ['slug' => $slug]);
            return $this->error('Resource not found', 404);
        }

        return $this->success(new ServiceResource($service), 'Service retrieved successfully');
    }

    /**
     * Create a new service
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

        $this->authorize('create_services');

        $validationRules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'slug' => 'required|string|max:255|unique:services,slug',
            'cover_photo' => 'required|image|max:4096',
            'is_active' => 'sometimes|boolean',
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

            // Handle cover photo upload
            if ($request->hasFile('cover_photo')) {
                $data['cover_photo'] = $request->file('cover_photo')->store('services/covers', 'public');
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

            // Remove sections and other non-model fields from data before creating service
            unset($data['sections'], $data['discipline_ids'], $data['disciplines']);
            
            // Remove old format fields if they exist
            for ($i = 1; $i <= 20; $i++) {
                unset($data["content{$i}"], $data["image{$i}"], $data["caption{$i}"]);
            }

            $service = Service::create($data);

            // Create sections
            if (!empty($sections)) {
                foreach ($sections as $index => $sectionData) {
                    $sectionToCreate = [
                        'service_id' => $service->id,
                        'content' => $sectionData['content'] ?? null,
                        'caption' => $sectionData['caption'] ?? null,
                        'order' => $sectionData['order'] ?? $index,
                    ];

                    // Handle section image upload
                    if (isset($sectionData['image']) && $sectionData['image'] instanceof \Illuminate\Http\UploadedFile) {
                        $imagePath = $sectionData['image']->store('services/sections', 'public');
                        StorageHelper::syncToPublic($imagePath);
                        $sectionToCreate['image'] = $imagePath;
                    }

                    $service->sections()->create($sectionToCreate);
                }
            }

            // Sync disciplines if provided (accepts both 'discipline_ids' and 'disciplines')
            $disciplineField = $request->has('discipline_ids') ? 'discipline_ids' : ($request->has('disciplines') ? 'disciplines' : null);
            if ($disciplineField) {
                $disciplineIds = array_filter($request->input($disciplineField), function($id) {
                    return is_numeric($id) && Discipline::where('id', $id)->exists();
                });
                $service->disciplines()->sync($disciplineIds);
            }

            return $this->success(new ServiceResource($service->load(['author', 'disciplines', 'sections'])), 'Service created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Service creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Update a service
     *
     * @param Request $request
     * @param Service $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $encodedId)
    {
        $this->authorize('edit_services');

        $service = Service::findByEncodedIdOrFail($encodedId);
        
        $validationRules = [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'slug' => 'sometimes|required|string|max:255|unique:services,slug,' . $service->id,
            'cover_photo' => 'sometimes|required|image|max:4096',
            'is_active' => 'sometimes|boolean',
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
            Log::error('Service validation failed', ['errors' => $validator->errors()->toArray()]);
            return $this->error('Unable to process request', 422);
        }

        try {
            $data = $validator->validated();

            // Handle cover photo upload
            if ($request->hasFile('cover_photo')) {
                // Delete old cover photo if exists
                if ($service->cover_photo) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($service->cover_photo);
                }
                $data['cover_photo'] = $request->file('cover_photo')->store('services/covers', 'public');
                // Sync to web-accessible storage
                StorageHelper::syncToPublic($data['cover_photo']);
            }

            // Handle sections update - support both old format (content1, image1, caption1) and new format
            $sections = $data['sections'] ?? [];
            $hasOldFormatSections = false;
            
            // Get existing sections to preserve images
            $existingSections = $service->sections->keyBy('order')->toArray();
            
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
                        // If image is sent as string (existing path), preserve it
                        elseif ($request->has($imageKey) && is_string($request->input($imageKey))) {
                            $sectionData['existing_image'] = $request->input($imageKey);
                        }
                        // Otherwise, try to preserve from existing section
                        elseif (isset($existingSections[$i - 1]['image'])) {
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
                foreach ($service->sections as $section) {
                    if ($section->image) {
                        $imagesToDelete[$section->order] = $section->image;
                    }
                }
                
                // Delete all existing sections (but keep track of images)
                $service->sections()->delete();

                // Create new sections
                foreach ($sections as $index => $sectionData) {
                    $sectionToCreate = [
                        'service_id' => $service->id,
                        'content' => $sectionData['content'] ?? null,
                        'caption' => $sectionData['caption'] ?? null,
                        'order' => $sectionData['order'] ?? $index,
                    ];

                    // Handle section image upload (new file)
                    if (isset($sectionData['image']) && $sectionData['image'] instanceof \Illuminate\Http\UploadedFile) {
                        $imagePath = $sectionData['image']->store('services/sections', 'public');
                        StorageHelper::syncToPublic($imagePath);
                        $sectionToCreate['image'] = $imagePath;
                        
                        // Delete old image for this order if it exists and is different
                        $order = $sectionData['order'] ?? $index;
                        if (isset($imagesToDelete[$order])) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($imagesToDelete[$order]);
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

                    $service->sections()->create($sectionToCreate);
                }
                
                // Delete any remaining old images that weren't preserved
                foreach ($imagesToDelete as $imagePath) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
                }
            }

            // Remove sections and discipline fields from data before updating service
            unset($data['sections'], $data['discipline_ids'], $data['disciplines']);
            
            // Remove old format fields if they exist
            for ($i = 1; $i <= 20; $i++) {
                unset($data["content{$i}"], $data["image{$i}"], $data["caption{$i}"]);
            }

            $service->update($data);

            // Sync disciplines if provided (accepts both 'discipline_ids' and 'disciplines')
            $disciplineField = $request->has('discipline_ids') ? 'discipline_ids' : ($request->has('disciplines') ? 'disciplines' : null);
            if ($disciplineField) {
                $disciplineIds = array_filter($request->input($disciplineField), function($id) {
                    return is_numeric($id) && Discipline::where('id', $id)->exists();
                });
                $service->disciplines()->sync($disciplineIds);
            }

            return $this->success(new ServiceResource($service->fresh()->load(['author', 'disciplines', 'sections'])), 'Service updated successfully');
        } catch (\Exception $e) {
            Log::error('Service update failed', ['error' => $e->getMessage(), 'service_id' => $encodedId, 'trace' => $e->getTraceAsString()]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Delete a service
     *
     * @param Service $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($encodedId)
    {
        $this->authorize('delete_services');

        try {
            $service = Service::findByEncodedIdOrFail($encodedId);
            $service->delete();
            return $this->success(null, 'Service deleted successfully');
        } catch (\Exception $e) {
            Log::error('Service deletion failed', ['error' => $e->getMessage(), 'service_id' => $encodedId]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Toggle service active status
     *
     * @param Service $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive($encodedId)
    {
        $this->authorize('edit_services');

        try {
            $service = Service::findByEncodedIdOrFail($encodedId);
            $service->update(['is_active' => !$service->is_active]);
            
            $status = $service->is_active ? 'activated' : 'deactivated';
            return $this->success([
                'service' => [
                    'id' => $service->encoded_id,
                    'title' => $service->title,
                    'is_active' => $service->is_active,
                ]
            ], "Service {$status} successfully");
        } catch (\Exception $e) {
            Log::error('Service status toggle failed', ['error' => $e->getMessage(), 'service_id' => $encodedId]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Bulk delete services
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('delete_services');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            Log::error('Service validation failed', ['errors' => $validator->errors()->toArray()]);
            return $this->error('Unable to process request', 422);
        }

        try {
            $deletedCount = 0;
            $errors = [];

            foreach ($request->ids as $encodedId) {
                try {
                    $service = Service::findByEncodedId($encodedId);
                    if ($service) {
                        // Delete cover photo if exists
                        if ($service->cover_photo) {
                            StorageHelper::deleteFromDirectory($service->cover_photo);
                        }
                        // Delete section images if exist
                        foreach ($service->sections as $section) {
                            if ($section->image) {
                                StorageHelper::deleteFromDirectory($section->image);
                            }
                        }
                        $service->delete();
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Service bulk delete item failed', ['error' => $e->getMessage(), 'service_id' => $encodedId]);
                    $errors[] = "Failed to delete service";
                }
            }

            return $this->success([
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], "{$deletedCount} service(s) deleted successfully");
        } catch (\Exception $e) {
            Log::error('Service bulk delete failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }

    /**
     * Bulk update service status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('edit_services');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string',
            'status' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            Log::error('Service validation failed', ['errors' => $validator->errors()->toArray()]);
            return $this->error('Unable to process request', 422);
        }

        try {
            $updatedCount = 0;
            $errors = [];

            foreach ($request->ids as $encodedId) {
                try {
                    $service = Service::findByEncodedId($encodedId);
                    if ($service) {
                        $service->is_active = $request->status;
                        $service->save();
                        $updatedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Service bulk status update item failed', ['error' => $e->getMessage(), 'service_id' => $encodedId]);
                    $errors[] = "Failed to update service";
                }
            }

            $statusText = $request->status ? 'activated' : 'deactivated';
            return $this->success([
                'updated_count' => $updatedCount,
                'errors' => $errors
            ], "{$updatedCount} service(s) {$statusText} successfully");
        } catch (\Exception $e) {
            Log::error('Service bulk status update failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }
}
