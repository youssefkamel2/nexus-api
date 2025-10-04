<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Http\Resources\SettingsResource;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Validator;
use App\Helpers\StorageHelper;

class SettingsController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        $this->authorize('view_settings');
        $settings = Setting::firstOrCreate(['id' => 1]);
        return $this->success(new SettingsResource($settings), 'Settings retrieved successfully');
    }

    // no need for store function as settings table has only one row
    public function update(Request $request)
    {
        $this->authorize('edit_settings');
        $validator = Validator::make($request->all(), [
            'our_mission' => 'required|string',
            'our_vision' => 'required|string',
            'years' => 'required|integer',
            'projects' => 'required|integer',
            'clients' => 'required|integer',
            'engineers' => 'required|integer',
            'portfolio' => 'required|string',
            'image' => 'sometimes|image|max:6096',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();

            $setting = Setting::first();

            // Handle image upload using StorageHelper
            if ($request->hasFile('image')) {
                // Delete old image using StorageHelper
                if ($setting && $setting->image) {
                    StorageHelper::deleteFromDirectory($setting->image);
                }
                $data['image'] = $request->file('image')->store('settings', 'public');
                StorageHelper::syncToPublic($data['image']);
            }

            $setting = Setting::updateOrCreate(['id' => 1], $data);
            return $this->success(new SettingsResource($setting), 'Setting updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update setting: ' . $e->getMessage(), 500);
        }
    }



}