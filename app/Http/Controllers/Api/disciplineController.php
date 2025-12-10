<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\Discipline;

class disciplineController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        $disciplines = Discipline::where('is_active', true)
            ->ordered()
            ->with('sections')
            ->get()
            ->map(function ($discipline) {
                return [
                    'id' => $discipline->encoded_id,
                    'title' => $discipline->title,
                    'slug' => $discipline->slug,
                    'description' => $discipline->description,
                    'cover_photo' => $discipline->cover_photo ? env('APP_URL') . '/storage/' . $discipline->cover_photo : null,
                    'show_on_home' => $discipline->show_on_home,
                    'order' => $discipline->order,
                    'is_active' => $discipline->is_active,
                    'sections' => $discipline->sections->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'content' => $section->content,
                            'image' => $section->image ? env('APP_URL') . '/storage/' . $section->image : null,
                            'caption' => $section->caption,
                            'order' => $section->order,
                        ];
                    }),
                ];
            });
        return $this->success($disciplines, 'Disciplines retrieved successfully');
    }

    public function show($encodedId)
    {
        try {
            $discipline = Discipline::findByEncodedIdOrFail($encodedId);
            
            if (!$discipline->is_active) {
                return $this->error('Discipline not found', 404);
            }

            $discipline->load('sections');

            return $this->success([
                'id' => $discipline->encoded_id,
                'title' => $discipline->title,
                'slug' => $discipline->slug,
                'description' => $discipline->description,
                'cover_photo' => $discipline->cover_photo ? env('APP_URL') . '/storage/' . $discipline->cover_photo : null,
                'show_on_home' => $discipline->show_on_home,
                'order' => $discipline->order,
                'is_active' => $discipline->is_active,
                'sections' => $discipline->sections->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'content' => $section->content,
                        'image' => $section->image ? env('APP_URL') . '/storage/' . $section->image : null,
                        'caption' => $section->caption,
                        'order' => $section->order,
                    ];
                }),
                'created_at' => $discipline->created_at,
                'updated_at' => $discipline->updated_at,
            ], 'Discipline retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Discipline not found', 404);
        }
    }
}
