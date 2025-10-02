<?php

namespace App\Http\Controllers\APi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\Feedback;
use App\Models\Project;

class HomeController extends Controller
{
    // one api return : only 4 services, all testimonials, only 6 projects
    public function index()
    {
        $services = Service::active()->limit(4)->get();
        $testimonials = Feedback::active()->get();
        $projects = Project::active()->limit(6)->get();
        return response()->json([
            'services' => $services,
            'testimonials' => $testimonials,
            'projects' => $projects,
        ]);
    }
}
