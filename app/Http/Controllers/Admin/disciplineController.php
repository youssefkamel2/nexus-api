<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Discipline;
use App\Models\User;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Helpers\StorageHelper;


class disciplineController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index()
    {
        $this->authorize('view_disciplines');

        $disciplines = Discipline::with(['createdBy', 'sections'])->ordered()->get()->map(function ($discipline) {
            return [
                'id' => $discipline->encoded_id,
                'title' => $discipline->title,
                'slug' => $discipline->slug,
                'description' => $discipline->description,
                'cover_photo' => $discipline->cover_photo ? env('APP_URL') . '/storage/' . $discipline->cover_photo : null,
                'show_on_home' => $discipline->show_on_home,
                'order' => $discipline->order,
                'is_active' => $discipline->is_active,
                'created_by' => $discipline->createdBy->name,
                'created_at' => $discipline->created_at,
                'updated_at' => $discipline->updated_at,
            ];
        });
        
        return $this->success($disciplines, 'Disciplines retrieved successfully');
    }

    public function show($encodedId)
    {
        $this->authorize('view_disciplines');

        $discipline = Discipline::findByEncodedIdOrFail($encodedId);
        $discipline->load(['createdBy', 'sections']);
        
        return $this->success([
            'id' => $discipline->encoded_id,
            'title' => $discipline->title,
            'slug' => $discipline->slug,
            'description' => $discipline->description,
            'cover_photo' => $discipline->cover_photo ? env('APP_URL') . '/storage/' . $discipline->cover_photo : null,
            'show_on_home' => $discipline->show_on_home,
            'order' => $discipline->order,
            'is_active' => $discipline->is_active,
            'sections' => $discipline->sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'content' => $section->content,
                    'image' => $section->image ? env('APP_URL') . '/storage/' . $section->image : null,
                    'caption' => $section->caption,
                    'order' => $section->order,
                ];
            }),
            'created_by' => [
                'id' => $discipline->createdBy->encoded_id,
                'name' => $discipline->createdBy->name,
            ],
            'created_at' => $discipline->created_at,
            'updated_at' => $discipline->updated_at,
        ], 'Discipline retrieved successfully');
    }

    public function store(Request $request)
    {
        $this->authorize('create_disciplines');

        $validationRules = [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:disciplines,slug',
            'description' => 'nullable|string',
            'cover_photo' => 'nullable|image|max:4096',
            'show_on_home' => 'sometimes|boolean',
            'order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
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
            $data['created_by'] = auth()->id();
            
            // Sanitize: Convert string "null" to actual null
            if (isset($data['description']) && $data['description'] === 'null') {
                $data['description'] = null;
            }

            // Auto-generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['title']);
            }

            // Handle cover photo upload
            if ($request->hasFile('cover_photo')) {
                $data['cover_photo'] = $request->file('cover_photo')->store('disciplines/covers', 'public');
                StorageHelper::syncToPublic($data['cover_photo']);
            }

            // Handle sections - support old format (content1, image1, caption1) 
            $sections = $data['sections'] ?? [];
            
            // Convert old format to new format if sections array is empty
            if (empty($sections)) {
                for ($i = 1; $i <= 20; $i++) {
                    $contentKey = "content{$i}";
                    $imageKey = "image{$i}";
                    $captionKey = "caption{$i}";
                    
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

            // Remove sections and other non-model fields from data before creating discipline
            unset($data['sections']);
            
            // Remove old format fields if they exist
            for ($i = 1; $i <= 20; $i++) {
                unset($data["content{$i}"], $data["image{$i}"], $data["caption{$i}"]);
            }

            $discipline = Discipline::create($data);

            // Create sections
            if (!empty($sections)) {
                foreach ($sections as $index => $sectionData) {
                    $sectionToCreate = [
                        'discipline_id' => $discipline->id,
                        'content' => $sectionData['content'] ?? null,
                        'caption' => $sectionData['caption'] ?? null,
                        'order' => $sectionData['order'] ?? $index,
                    ];

                    // Handle section image upload
                    if (isset($sectionData['image']) && $sectionData['image'] instanceof \Illuminate\Http\UploadedFile) {
                        $imagePath = $sectionData['image']->store('disciplines/sections', 'public');
                        StorageHelper::syncToPublic($imagePath);
                        $sectionToCreate['image'] = $imagePath;
                    }

                    $discipline->sections()->create($sectionToCreate);
                }
            }

            return $this->success($discipline->load(['createdBy', 'sections']), 'Discipline created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Discipline creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('Operation failed', 500);
        }
    }

    public function update(Request $request, $encodedId)
    {
        $this->authorize('edit_disciplines');
        
        $discipline = Discipline::findByEncodedIdOrFail($encodedId);

        $validationRules = [
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:disciplines,slug,' . $discipline->id,
            'description' => 'sometimes|nullable|string',
            'cover_photo' => 'sometimes|nullable|image|max:4096',
            'show_on_home' => 'sometimes|boolean',
            'order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
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
                // Delete old cover photo if exists
                if ($discipline->cover_photo) {
                    Storage::disk('public')->delete($discipline->cover_photo);
                }
                $data['cover_photo'] = $request->file('cover_photo')->store('disciplines/covers', 'public');
                StorageHelper::syncToPublic($data['cover_photo']);
            }

            // Handle sections update - support both old format and new format
            $sections = $data['sections'] ?? [];
            $hasOldFormatSections = false;
            
            // Get existing sections to preserve images
            $existingSections = $discipline->sections->keyBy('order')->toArray();
            
            // Convert old format to new format if sections array is empty
            if (empty($sections)) {
                for ($i = 1; $i <= 20; $i++) {
                    $contentKey = "content{$i}";
                    $imageKey = "image{$i}";
                    $captionKey = "caption{$i}";
                    
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
                // Collect images to delete
                $imagesToDelete = [];
                foreach ($discipline->sections as $section) {
                    if ($section->image) {
                        $imagesToDelete[$section->order] = $section->image;
                    }
                }
                
                // Delete all existing sections
                $discipline->sections()->delete();

                // Create new sections
                foreach ($sections as $index => $sectionData) {
                    $sectionToCreate = [
                        'discipline_id' => $discipline->id,
                        'content' => $sectionData['content'] ?? null,
                        'caption' => $sectionData['caption'] ?? null,
                        'order' => $sectionData['order'] ?? $index,
                    ];

                    // Handle section image upload (new file)
                    if (isset($sectionData['image']) && $sectionData['image'] instanceof \Illuminate\Http\UploadedFile) {
                        $imagePath = $sectionData['image']->store('disciplines/sections', 'public');
                        StorageHelper::syncToPublic($imagePath);
                        $sectionToCreate['image'] = $imagePath;
                        
                        // Delete old image for this order if it exists
                        $order = $sectionData['order'] ?? $index;
                        if (isset($imagesToDelete[$order])) {
                            Storage::disk('public')->delete($imagesToDelete[$order]);
                            unset($imagesToDelete[$order]);
                        }
                    }
                    // If delete_image flag is set, don't set image and delete old image
                    elseif (isset($sectionData['delete_image']) && $sectionData['delete_image']) {
                        $sectionToCreate['image'] = null;
                        
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

                    $discipline->sections()->create($sectionToCreate);
                }
                
                // Delete any remaining old images that weren't preserved
                foreach ($imagesToDelete as $imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            // Remove sections and old format fields from data before updating discipline
            unset($data['sections']);
            
            for ($i = 1; $i <= 20; $i++) {
                unset($data["content{$i}"], $data["image{$i}"], $data["caption{$i}"]);
            }

            $discipline->update($data);

            return $this->success($discipline->fresh()->load(['createdBy', 'sections']), 'Discipline updated successfully');
        } catch (\Exception $e) {
            Log::error('Discipline update failed', ['error' => $e->getMessage(), 'discipline_id' => $encodedId, 'trace' => $e->getTraceAsString()]);
            return $this->error('Operation failed', 500);
        }
    }

    public function destroy($encodedId)
    {
        $this->authorize('delete_disciplines');

        try {
            $discipline = Discipline::findByEncodedIdOrFail($encodedId);
            
            // Delete cover photo if exists
            if ($discipline->cover_photo) {
                Storage::disk('public')->delete($discipline->cover_photo);
            }
            
            // Delete section images
            foreach ($discipline->sections as $section) {
                if ($section->image) {
                    Storage::disk('public')->delete($section->image);
                }
            }
            
            $discipline->delete();
            return $this->success(null, 'Discipline deleted successfully');
        } catch (\Exception $e) {
            Log::error('Discipline deletion failed', ['error' => $e->getMessage(), 'discipline_id' => $encodedId]);
            return $this->error('Operation failed', 500);
        }
    }

    public function toggleActive($encodedId)
    {
        $this->authorize('edit_disciplines');

        try {
            $discipline = Discipline::findByEncodedIdOrFail($encodedId);
            $discipline->is_active = !$discipline->is_active;
            $discipline->save();
            return $this->success($discipline, 'Discipline active status toggled successfully');
        } catch (\Exception $e) {
            Log::error('Discipline toggle failed', ['error' => $e->getMessage(), 'discipline_id' => $encodedId]);
            return $this->error('Operation failed', 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('delete_disciplines');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->error('Unable to process request', 422);
        }

        try {
            $deletedCount = 0;
            $errors = [];

            foreach ($request->ids as $encodedId) {
                try {
                    $discipline = Discipline::findByEncodedId($encodedId);
                    if ($discipline) {
                        // Delete cover photo if exists
                        if ($discipline->cover_photo) {
                            Storage::disk('public')->delete($discipline->cover_photo);
                        }
                        
                        // Delete section images if exist
                        foreach ($discipline->sections as $section) {
                            if ($section->image) {
                                Storage::disk('public')->delete($section->image);
                            }
                        }
                        
                        $discipline->delete();
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to delete discipline with ID {$encodedId}";
                }
            }

            if ($deletedCount === 0 && !empty($errors)) {
                return $this->error('Failed to delete selected disciplines', 500);
            }

            return $this->success([
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], 'Selected disciplines deleted successfully');

        } catch (\Exception $e) {
            Log::error('Bulk delete disciplines failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }

    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('edit_disciplines');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string',
            'is_active' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->error('Unable to process request', 422);
        }

        try {
            $updatedCount = 0;
            $errors = [];

            foreach ($request->ids as $encodedId) {
                try {
                    $discipline = Discipline::findByEncodedId($encodedId);
                    if ($discipline) {
                        $discipline->update(['is_active' => $request->is_active]);
                        $updatedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to update discipline with ID {$encodedId}";
                }
            }

            return $this->success([
                'updated_count' => $updatedCount,
                'errors' => $errors
            ], 'Selected disciplines status updated successfully');

        } catch (\Exception $e) {
            Log::error('Bulk update disciplines status failed', ['error' => $e->getMessage()]);
            return $this->error('Operation failed', 500);
        }
    }
}
