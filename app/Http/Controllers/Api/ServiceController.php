<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Traits\ResponseTrait;

class ServiceController extends Controller
{
    use ResponseTrait;

    /**
     * Get all active services
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $services = Service::with('author')->active()->get();
        return $this->success(ServiceResource::collection($services), 'Services retrieved successfully');
    }

    /**
     * Get service by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySlug($slug)
    {
        $service = Service::with('author')->active()->bySlug($slug)->first();

        if (!$service) {
            return $this->error('Service not found', 404);
        }

        return $this->success(new ServiceResource($service), 'Service retrieved successfully');
    }
}
