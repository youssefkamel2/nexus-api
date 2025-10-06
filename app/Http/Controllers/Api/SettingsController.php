<?php

namespace App\Http\Controllers\APi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\SettingsResource;
use App\Models\Setting;
use App\Traits\ResponseTrait;

class SettingsController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        $settings = Setting::first();
        return $this->success(new SettingsResource($settings), 'Settings retrieved successfully');
    }
}
