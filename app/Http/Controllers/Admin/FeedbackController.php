<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use App\Models\Feedback;
use Illuminate\Support\Facades\Validator;
use App\Helpers\StorageHelper;
use App\Http\Resources\FeedbackResource;

class FeedbackController extends Controller
{

    use ResponseTrait;

    public function index()
    {
        $this->authorize('view_feedbacks');
        $feedback = Feedback::all();
        return $this->success(FeedbackResource::collection($feedback), 'Feedbacks retrieved successfully');
    }

    public function store(Request $request)
    {
        $this->authorize('create_feedbacks');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'image' => 'nullable|image|max:4096',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();

            // Handle image upload using StorageHelper
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('feedback', 'public');
                StorageHelper::syncToPublic($data['image']);
            } else {
                // Set default image if no image provided
                $data['image'] = 'feedback/default.png';
            }

            $feedback = Feedback::create($data);
            return $this->success(new FeedbackResource($feedback), 'Feedback created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create feedback: ' . $e->getMessage(), 500);
        }
    }


    public function update(Request $request, $encodedId)
    {
        $this->authorize('edit_feedbacks');
        $feedback = Feedback::findByEncodedId($encodedId);

        if (!$feedback) {
            return $this->error('Feedback not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'image' => 'nullable|image|max:4096',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();

            // Handle image upload using StorageHelper
            if ($request->hasFile('image')) {
                // Delete old image using StorageHelper (but not default.png)
                if ($feedback->image && $feedback->image !== 'feedback/default.png') {
                    StorageHelper::deleteFromDirectory($feedback->image);
                }
                $data['image'] = $request->file('image')->store('feedback', 'public');
                StorageHelper::syncToPublic($data['image']);
            } elseif ($request->has('image') && $request->input('image') === null) {
                // If image is explicitly set to null, remove current and set default
                if ($feedback->image && $feedback->image !== 'feedback/default.png') {
                    StorageHelper::deleteFromDirectory($feedback->image);
                }
                $data['image'] = 'feedback/default.png';
            }

            $feedback->update($data);
            return $this->success(new FeedbackResource($feedback), 'Feedback updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update feedback: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($encodedId)
    {
        $this->authorize('delete_feedbacks');
        $feedback = Feedback::findByEncodedId($encodedId);

        if (!$feedback) {
            return $this->error('Feedback not found', 404);
        }

        $feedback->delete();
        return $this->success(null, 'Feedback deleted successfully');
    }

    // toggle active

    public function toggleActive($encodedId)
    {
        $this->authorize('toggle_active_feedbacks');
        $feedback = Feedback::findByEncodedId($encodedId);

        if (!$feedback) {
            return $this->error('Feedback not found', 404);
        }

        $feedback->is_active = !$feedback->is_active;
        $feedback->save();
        return $this->success(null, 'Feedback status updated successfully');
    }

    /**
     * Bulk delete feedbacks
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('delete_feedbacks');

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
                    $feedback = Feedback::findByEncodedId($encodedId);
                    if ($feedback) {
                        // Delete image if exists and not default
                        if ($feedback->image && $feedback->image !== 'feedback/default.png') {
                            StorageHelper::deleteFromDirectory($feedback->image);
                        }
                        $feedback->delete();
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to delete feedback {$encodedId}: " . $e->getMessage();
                }
            }

            return $this->success([
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], "{$deletedCount} feedback(s) deleted successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to delete feedbacks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk update feedback status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('toggle_active_feedbacks');

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
                    $feedback = Feedback::findByEncodedId($encodedId);
                    if ($feedback) {
                        $feedback->is_active = $request->status;
                        $feedback->save();
                        $updatedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to update feedback {$encodedId}: " . $e->getMessage();
                }
            }

            $statusText = $request->status ? 'activated' : 'deactivated';
            return $this->success([
                'updated_count' => $updatedCount,
                'errors' => $errors
            ], "{$updatedCount} feedback(s) {$statusText} successfully");
        } catch (\Exception $e) {
            return $this->error('Failed to update feedback status: ' . $e->getMessage(), 500);
        }
    }

}
