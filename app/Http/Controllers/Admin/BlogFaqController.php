<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogFaq;
use App\Http\Resources\BlogFaqResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Traits\ResponseTrait;

class BlogFaqController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware('permission:manage_blog_faqs');
    }

    public function index(Blog $blog)
    {
        $faqs = $blog->faqs()->orderBy('order')->get();
        return $this->success(BlogFaqResource::collection($faqs), 'FAQs fetched successfully');
    }

    public function store(Request $request, Blog $blog)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'answer' => 'required|string',
            'order' => 'nullable|integer',
        ]);
        
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $faq = $blog->faqs()->create($validator->validated());
        return $this->success(new BlogFaqResource($faq), 'FAQ created successfully', 201);
    }

    public function show(Blog $blog, $faq)
    {

        $faq = BlogFaq::find($faq);

        if (!$faq) {
            Log::warning('Blog FAQ not found', ['faq_id' => $faq]);
            return $this->error('Resource not found', 404);
        }

        // Ensure FAQ belongs to the blog
        if ($faq->blog_id !== $blog->id) {
            Log::warning('Blog FAQ mismatch', ['faq_id' => $faq->id, 'blog_id' => $blog->id]);
            return $this->error('Resource not found', 404);
        }

        
        return $this->success(new BlogFaqResource($faq), 'FAQ fetched successfully');
    }

    public function update(Request $request, Blog $blog, $faq)
    {

        $faq = BlogFaq::find($faq);

        if (!$faq) {
            Log::warning('Blog FAQ not found', ['faq_id' => $faq]);
            return $this->error('Resource not found', 404);
        }

        // Ensure FAQ belongs to the blog
        if ($faq->blog_id !== $blog->id) {
            Log::warning('Blog FAQ mismatch', ['faq_id' => $faq->id, 'blog_id' => $blog->id]);
            return $this->error('Resource not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'sometimes|string',
            'answer' => 'sometimes|string',
            'order' => 'nullable|integer',
        ]);
        
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $faq->update($validator->validated());
        return $this->success(new BlogFaqResource($faq), 'FAQ updated successfully');
    }

    public function destroy(Blog $blog, $faq)
    {
        $faq = BlogFaq::find($faq);

        if (!$faq) {
            Log::warning('Blog FAQ not found', ['faq_id' => $faq]);
            return $this->error('Resource not found', 404);
        }
        
        // Ensure FAQ belongs to the blog
        if ($faq->blog_id !== $blog->id) {
            Log::warning('Blog FAQ mismatch', ['faq_id' => $faq->id, 'blog_id' => $blog->id]);
            return $this->error('Resource not found', 404);
        }

        $faq->delete();
        return $this->success(null, 'FAQ deleted successfully');
    }
}