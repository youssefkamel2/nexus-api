<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    use ResponseTrait;

    /**
     * Get all active projects
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $projects = Project::with('author')->active()->get();
        return $this->success(ProjectResource::collection($projects), 'Projects retrieved successfully');
    }

    /**
     * Get project by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySlug($slug)
    {
        $project = Project::with('author')->active()->bySlug($slug)->first();

        if (!$project) {
            Log::warning('Public project not found', ['slug' => $slug]);
            return $this->error('Resource not found', 404);
        }

        return $this->success(new ProjectResource($project), 'Project retrieved successfully');
    }
}
