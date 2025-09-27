<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BlogMediaController extends Controller
{
    use ResponseTrait;

    public function uploadVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-ms-wmv,video/webm|max:102400', // Max 100MB
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $file = $request->file('video');
            $filename = 'video_' . Str::random(20) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('blog-videos', $filename, 'public');
            $url = asset('storage/' . $path);

            return $this->success([
                'url' => $url,
                'type' => 'video',
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ], 'Video uploaded successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to upload video: ' . $e->getMessage(), 500);
        }
    }

    public function extractYoutubeId($url)
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i';
        
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    public function processYoutubeUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|starts_with:https://www.youtube.com/,https://youtu.be/',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $youtubeId = $this->extractYoutubeId($request->url);

        if (!$youtubeId) {
            return $this->error('Invalid YouTube URL', 422);
        }

        return $this->success([
            'youtube_id' => $youtubeId,
            'embed_url' => 'https://www.youtube.com/embed/' . $youtubeId,
            'thumbnail' => 'https://img.youtube.com/vi/' . $youtubeId . '/hqdefault.jpg',
            'type' => 'youtube',
        ], 'YouTube URL processed successfully');
    }
}
