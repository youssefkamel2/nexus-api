<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\BlogResource;

class BlogController extends Controller
{
    use ResponseTrait;

    /**
     * Get all active blogs for public
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Blog::with('author')->active();

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Search by title
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('title', 'like', '%' . $search . '%');
        }

        $blogs = $query->orderBy('created_at', 'desc')->get();
        return $this->success(BlogResource::collection($blogs), 'Blogs retrieved successfully');
    }

    /**
     * Get blog by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySlug($slug)
    {
        $blog = Blog::with(['author', 'faqs'])->active()->bySlug($slug)->first();

        if (!$blog) {
            Log::warning('Public blog not found', ['slug' => $slug]);
            return $this->error('Resource not found', 404);
        }

        return $this->success(new BlogResource($blog), 'Blog retrieved successfully');
    }

    /**
     * Get blogs for landing page
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function landing()
    {
        // Get hero blog (marked as hero or latest)
        $hero = Blog::with(['author', 'faqs'])->active()->hero()->latest()->first();
        if (!$hero) {
            $hero = Blog::with(['author', 'faqs'])->active()->latest()->first();
        }

        $latest = Blog::with(['author', 'faqs'])->active()->latest()->take(3)->get();
        $news = Blog::with(['author', 'faqs'])->active()->byCategory('news')->latest()->take(3)->get();
        $trending = Blog::with(['author', 'faqs'])->active()->byCategory('trending')->latest()->take(3)->get();

        return $this->success([
            'hero' => $hero ? new BlogResource($hero) : null,
            'latest' => BlogResource::collection($latest),
            'news' => BlogResource::collection($news),
            'trending' => BlogResource::collection($trending),
        ], 'Landing blogs retrieved successfully');
    }

    /**
     * Get recent blogs (simplified)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recent()
    {
        $blogs = Blog::active()
            ->select('id', 'slug', 'cover_photo', 'category', 'title', 'created_at', 'updated_at')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($blog) {
                return [
                    'id' => $blog->encoded_id,
                    'slug' => $blog->slug,
                    'title' => $blog->title,
                    'category' => $blog->category,
                    'cover_photo' => $blog->cover_photo_url,
                    'created_at' => $blog->created_at,
                    'updated_at' => $blog->updated_at,
                ];
            });

        return $this->success($blogs, 'Recent blogs retrieved successfully');
    }

    /**
     * Get related blogs
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function related($slug)
    {
        $blog = Blog::active()->bySlug($slug)->first();
        
        if (!$blog) {
            Log::warning('Public blog not found', ['slug' => $slug]);
            return $this->error('Resource not found', 404);
        }

        $relatedBlogs = Blog::active()
            ->where('id', '!=', $blog->id)
            ->byCategory($blog->category)
            ->select('id', 'slug', 'cover_photo', 'title', 'category', 'created_at', 'updated_at')
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($blog) {
                return [
                    'id' => $blog->encoded_id,
                    'slug' => $blog->slug,
                    'title' => $blog->title,
                    'category' => $blog->category,
                    'cover_photo' => $blog->cover_photo_url,
                    'created_at' => $blog->created_at,
                    'updated_at' => $blog->updated_at,
                ];
            });

        return $this->success($relatedBlogs, 'Related blogs retrieved successfully');
    }

    /**
     * Get blog categories
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories()
    {
        $categories = [
            'trending' => 'Trending',
            'news' => 'News'
        ];

        return $this->success($categories, 'Blog categories retrieved successfully');
    }
}
