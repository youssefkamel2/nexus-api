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
            'image' => 'required|image|max:4096',
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
            'image' => 'sometimes|image|max:4096',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();

            // Handle image upload using StorageHelper
            if ($request->hasFile('image')) {
                // Delete old image using StorageHelper
                if ($feedback->image) {
                    StorageHelper::deleteFromDirectory($feedback->image);
                }
                $data['image'] = $request->file('image')->store('feedback', 'public');
                StorageHelper::syncToPublic($data['image']);
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

}
