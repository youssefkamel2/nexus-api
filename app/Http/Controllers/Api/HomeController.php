<?php

namespace App\Http\Controllers\APi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\Feedback;
use App\Models\Project;
use App\Traits\ResponseTrait;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\FeedbackResource;
use App\Http\Resources\ProjectResource;

class HomeController extends Controller
{
    use ResponseTrait;
    // one api return : only 4 services, all testimonials, only 6 projects
    public function index()
    {
        $services = Service::with('author')->active()->limit(4)->get();
        $testimonials = Feedback::active()->get();
        $projects = Project::with('author')->active()->limit(6)->get();
        
        return $this->success([
            'services' => ServiceResource::collection($services),
            'testimonials' => FeedbackResource::collection($testimonials),
            'projects' => ProjectResource::collection($projects),
        ], 'Home page data retrieved successfully');
    }
}
