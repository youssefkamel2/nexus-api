<?php

namespace App\Http\Controllers\APi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\About;
use App\Http\Resources\AboutResource;
use App\Traits\ResponseTrait;

class AboutController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        $about = About::all();
        return $this->success(AboutResource::collection($about), 'About retrieved successfully');
    }
}
