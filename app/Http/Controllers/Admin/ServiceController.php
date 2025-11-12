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

        $services = Service::with(['author', 'disciplines'])->get()->map(function ($service) {
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
        return $this->success(new ServiceResource($service->load(['author', 'disciplines'])), 'Service retrieved successfully');
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

        $service = Service::with(['author', 'disciplines'])->bySlug($slug)->first();

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

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'slug' => 'required|string|max:255|unique:services,slug',
            'cover_photo' => 'required|image|max:4096',
            'content1' => 'nullable|string',
            'image1' => 'nullable|image|max:4096',
            'content2' => 'nullable|string',
            'image2' => 'nullable|image|max:4096',
            'content3' => 'nullable|string',
            'image3' => 'nullable|image|max:4096',
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

            // Handle cover photo upload
            if ($request->hasFile('cover_photo')) {
                $data['cover_photo'] = $request->file('cover_photo')->store('services/covers', 'public');
                // Sync to web-accessible storage
                StorageHelper::syncToPublic($data['cover_photo']);
            }

            // Handle section images
            for ($i = 1; $i <= 3; $i++) {
                if ($request->hasFile("image{$i}")) {
                    $data["image{$i}"] = $request->file("image{$i}")->store("services/sections", 'public');
                    // Sync to web-accessible storage
                    StorageHelper::syncToPublic($data["image{$i}"]);
                }
            }


            $service = Service::create($data);

            // Sync disciplines if provided (accepts both 'discipline_ids' and 'disciplines')
            $disciplineField = $request->has('discipline_ids') ? 'discipline_ids' : ($request->has('disciplines') ? 'disciplines' : null);
            if ($disciplineField) {
                $disciplineIds = array_filter($request->input($disciplineField), function($id) {
                    return is_numeric($id) && Discipline::where('id', $id)->exists();
                });
                $service->disciplines()->sync($disciplineIds);
            }

            return $this->success(new ServiceResource($service->load(['author', 'disciplines'])), 'Service created successfully', 201);
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
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'slug' => 'sometimes|required|string|max:255|unique:services,slug,' . $service->id,
            'cover_photo' => 'sometimes|required|image|max:4096',
            'content1' => 'nullable|string',
            'image1' => 'nullable|image|max:4096',
            'content2' => 'nullable|string',
            'image2' => 'nullable|image|max:4096',
            'content3' => 'nullable|string',
            'image3' => 'nullable|image|max:4096',
            'is_active' => 'sometimes|boolean',
            'discipline_ids' => 'sometimes|array',
            'discipline_ids.*' => 'required|integer|exists:disciplines,id',
            'disciplines' => 'sometimes|array',
            'disciplines.*' => 'required|integer|exists:disciplines,id',
        ]);

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

            // Handle section images - check if they should be removed or updated
            for ($i = 1; $i <= 3; $i++) {
                $imageKey = "image{$i}";
                
                if ($request->hasFile($imageKey)) {
                    // Delete old image if exists
                    if ($service->$imageKey) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($service->$imageKey);
                    }
                    // Upload new image
                    $data[$imageKey] = $request->file($imageKey)->store("services/sections", 'public');
                    // Sync to web-accessible storage
                    StorageHelper::syncToPublic($data[$imageKey]);
                } elseif ($request->has($imageKey) && $request->input($imageKey) === null) {
                    // If image field is explicitly set to null, remove the existing image
                    if ($service->$imageKey) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($service->$imageKey);
                    }
                    $data[$imageKey] = null;
                }
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

            return $this->success(new ServiceResource($service->fresh()->load(['author', 'disciplines'])), 'Service updated successfully');
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
                        // Delete images if exist
                        if ($service->cover_photo) {
                            StorageHelper::deleteFromDirectory($service->cover_photo);
                        }
                        if ($service->image1) {
                            StorageHelper::deleteFromDirectory($service->image1);
                        }
                        if ($service->image2) {
                            StorageHelper::deleteFromDirectory($service->image2);
                        }
                        if ($service->image3) {
                            StorageHelper::deleteFromDirectory($service->image3);
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
