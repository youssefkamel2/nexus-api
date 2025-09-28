<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\BlogResource;
use App\Helpers\StorageHelper;

class BlogController extends Controller
{
    use ResponseTrait;

    /**
     * Process content to update dynamic dates to current year
     * 
     * @param string $content
     * @return string
     */
    private function processDynamicDates($content)
    {
        if (empty($content)) {
            return $content;
        }

        $currentYear = date('Y');
        $pattern = '/<span\s+class=[\'\"]dynamic-date[\'\"]>(\d{4})<\/span>/i';
        
        return preg_replace_callback($pattern, function($matches) use ($currentYear) {
            return str_replace($matches[1], $currentYear, $matches[0]);
        }, $content);
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get all blogs for admin
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        
        $this->authorize('view_blogs');

        $query = Blog::with('author');

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Search by title
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('title', 'like', '%' . $search . '%');
        }

        $blogs = $query->orderBy('created_at', 'desc')->get()->map(function ($blog) {
            return [
                'id' => $blog->encoded_id,
                'title' => $blog->title,
                'slug' => $blog->slug,
                'cover_photo' => $blog->cover_photo_url,
                'category' => $blog->category,
                'mark_as_hero' => $blog->mark_as_hero,
                'is_active' => $blog->is_active,
                'tags' => $blog->tags,
                'author' => [
                    'id' => $blog->author->encoded_id,
                    'name' => $blog->author->name,
                    'email' => $blog->author->email,
                ],
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
            ];
        });

        return $this->success($blogs, 'Blogs retrieved successfully');
    }

    /**
     * Store a new blog
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create_blogs');

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:blogs,slug',
            'cover_photo' => 'required|image|max:4096',
            'category' => 'required|in:trending,news',
            'content' => 'required|string',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
            'headings' => 'sometimes',
            'mark_as_hero' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();
            
            if (isset($data['headings'])) {
                if (is_string($data['headings'])) {
                    $data['headings'] = json_decode($data['headings'], true);
                }
            }
            
            $data['created_by'] = auth()->id();
            
            // Process dynamic dates in content
            if (!empty($data['content'])) {
                $data['content'] = $this->processDynamicDates($data['content']);
            }
            
            // Handle cover photo upload using StorageHelper
            if ($request->hasFile('cover_photo')) {
                $data['cover_photo'] = $request->file('cover_photo')->store('blogs', 'public');
                StorageHelper::syncToPublic($data['cover_photo']);
            }
            
            $blog = Blog::create($data);

            return $this->success(new BlogResource($blog->load('author')), 'Blog created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create blog: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific blog
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($encodedId)
    {
        $this->authorize('view_blogs');

        $blog = Blog::findByEncodedIdOrFail($encodedId);
        return $this->success(new BlogResource($blog->load(['author', 'faqs'])), 'Blog retrieved successfully');
    }

    /**
     * Get blog by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySlug($slug)
    {
        $this->authorize('view_blogs');

        $blog = Blog::with(['author', 'faqs'])->bySlug($slug)->first();

        if (!$blog) {
            return $this->error('Blog not found', 404);
        }

        return $this->success(new BlogResource($blog), 'Blog retrieved successfully');
    }

    /**
     * Update a blog
     *
     * @param Request $request
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $encodedId)
    {
        $this->authorize('edit_blogs');

        $blog = Blog::findByEncodedIdOrFail($encodedId);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:blogs,slug,' . $blog->id,
            'cover_photo' => 'sometimes|image|max:4096',
            'category' => 'sometimes|in:trending,news',
            'content' => 'sometimes|string',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
            'headings' => 'sometimes',
            'mark_as_hero' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $data = $validator->validated();
            
            if (isset($data['headings'])) {
                if (is_string($data['headings'])) {
                    $data['headings'] = json_decode($data['headings'], true);
                }
            }

            // Process dynamic dates in content
            if (!empty($data['content'])) {
                $data['content'] = $this->processDynamicDates($data['content']);
            }
            
            // Handle cover photo upload using StorageHelper
            if ($request->hasFile('cover_photo')) {
                // Delete old image using StorageHelper
                if ($blog->cover_photo) {
                    StorageHelper::deleteFromPublic($blog->cover_photo);
                }
                $data['cover_photo'] = $request->file('cover_photo')->store('blogs', 'public');
                StorageHelper::syncToPublic($data['cover_photo']);
            }
            
            $blog->update($data);
            return $this->success(new BlogResource($blog->fresh()->load('author')), 'Blog updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update blog: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a blog
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($encodedId)
    {
        $this->authorize('delete_blogs');

        $blog = Blog::findByEncodedIdOrFail($encodedId);

        try {
            // Delete cover photo using StorageHelper
            if ($blog->cover_photo) {
                StorageHelper::deleteFromPublic($blog->cover_photo);
            }
            
            $blog->delete();
            return $this->success(null, 'Blog deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete blog: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle blog active status
     *
     * @param string $encodedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive($encodedId)
    {
        $this->authorize('edit_blogs');

        $blog = Blog::findByEncodedIdOrFail($encodedId);
        $blog->is_active = !$blog->is_active;
        $blog->save();

        return $this->success([
            'id' => $blog->encoded_id,
            'is_active' => $blog->is_active
        ], 'Blog status updated successfully');
    }

    /**
     * Get blog options for admin forms
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOptions()
    {
        $this->authorize('view_blogs');

        try {
            $options = [
                'categories' => [
                    'trending' => 'Trending',
                    'news' => 'News'
                ],
                'validation_rules' => [
                    'title' => 'required|string|max:255',
                    'slug' => 'required|string|max:255|unique:blogs,slug',
                    'cover_photo' => 'required|image|max:4096',
                    'category' => 'required|in:trending,news',
                    'content' => 'required|string',
                    'tags' => 'sometimes|array',
                    'headings' => 'sometimes',
                    'mark_as_hero' => 'sometimes|boolean',
                    'is_active' => 'sometimes|boolean'
                ]
            ];

            return $this->success($options, 'Blog options retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk delete blogs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('delete_blogs');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:blogs,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $blogs = Blog::whereIn('id', $request->ids)->get();
            
            foreach ($blogs as $blog) {
                // Delete cover photo using StorageHelper
                if ($blog->cover_photo) {
                    StorageHelper::deleteFromPublic($blog->cover_photo);
                }
                $blog->delete();
            }

            return $this->success(null, count($request->ids) . ' blogs deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete blogs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk update blog status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->authorize('edit_blogs');

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:blogs,id',
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $count = Blog::whereIn('id', $request->ids)->update(['is_active' => $request->status]);

            return $this->success(null, 'Status updated for ' . $count . ' blogs');
        } catch (\Exception $e) {
            return $this->error('Failed to update blog statuses: ' . $e->getMessage(), 500);
        }
    }

    public function bulkUpdateCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:blogs,id',
            'category' => 'required|in:trending,news',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $count = Blog::whereIn('id', $request->ids)
                ->update(['category' => $request->category]);

            return $this->success(null, 'Category updated for ' . $count . ' blogs');
        } catch (\Exception $e) {
            return $this->error('Failed to update blog categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark blog as hero
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsHero(Request $request)
    {
        $this->authorize('edit_blogs');

        $validator = Validator::make($request->all(), [
            'encoded_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $blog = Blog::findByEncodedIdOrFail($request->encoded_id);
            
            // First reset all heroes
            Blog::where('mark_as_hero', true)->update(['mark_as_hero' => false]);
            
            // Set the new hero
            $blog->mark_as_hero = true;
            $blog->save();

            return $this->success(new BlogResource($blog->load('author')), 'Blog marked as hero successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update hero status: ' . $e->getMessage(), 500);
        }
    }

    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:blogs,id',
            'category' => 'sometimes|in:trending,news',
            'is_active' => 'sometimes|boolean',
            'mark_as_hero' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $updates = $request->only(['category', 'is_active', 'mark_as_hero']);
            
            // If marking as hero, first reset all heroes
            (isset($updates['mark_as_hero']) && $updates['mark_as_hero']) 
                ? Blog::where('mark_as_hero', true)->update(['mark_as_hero' => false])
                : null;

            $count = Blog::whereIn('id', $request->ids)->update($updates);

            return $this->success(null, 'Updated ' . $count . ' blogs');
        } catch (\Exception $e) {
            return $this->error('Failed to update blogs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload content image for blog editor
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadContentImage(Request $request)
    {
        $this->authorize('create_blogs');

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $path = $request->file('image')->store('blog-content', 'public');
            StorageHelper::syncToPublic($path);
            $url = asset('storage/' . $path);
            
            return $this->success(['url' => $url], 'Image uploaded successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to upload image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get blog statistics for admin dashboard
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        $this->authorize('view_blogs');

        try {
            $stats = [
                'total_blogs' => Blog::count(),
                'active_blogs' => Blog::active()->count(),
                'inactive_blogs' => Blog::where('is_active', false)->count(),
                'hero_blog' => Blog::hero()->count(),
                'blogs_by_category' => Blog::selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category'),
                'recent_blogs' => Blog::where('created_at', '>=', now()->subDays(30))->count(),
            ];

            return $this->success($stats, 'Blog statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }
}