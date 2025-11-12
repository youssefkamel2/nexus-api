<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Discipline;
use App\Models\User;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class disciplineController extends Controller
{
    use ResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index()
    {
        $this->authorize('view_disciplines');

        $disciplines = Discipline::with('createdBy')->get()->map(function ($discipline) {
            return [
                'id' => $discipline->id,
                'title' => $discipline->title,
                'is_active' => $discipline->is_active,
                'created_by' => $discipline->createdBy->name,
                'created_at' => $discipline->created_at,
                'updated_at' => $discipline->updated_at,
            ];
        });
        
        return $this->success($disciplines, 'Disciplines retrieved successfully');
    }

    public function store(Request $request)
    {
        $this->authorize('create_disciplines');

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        $data['created_by'] = auth()->id();


        $discipline = Discipline::create($data);
        return $this->success($discipline, 'Discipline created successfully');
    }

    public function update(Request $request, $id)
    {
        $this->authorize('edit_disciplines');
        
        $discipline = Discipline::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }
        
        $data = $validator->validated();

        $discipline->update($data);
        return $this->success($discipline, 'Discipline updated successfully');
    }

    public function destroy($id)
    {
        $this->authorize('delete_disciplines');

        $discipline = Discipline::findOrFail($id);
        $discipline->delete();
        return $this->success(null, 'Discipline deleted successfully');
    }

    public function toggleActive($id)
    {
        $this->authorize('edit_disciplines');

        $discipline = Discipline::findOrFail($id);
        $discipline->is_active = !$discipline->is_active;
        $discipline->save();
        return $this->success($discipline, 'Discipline active status toggled successfully');
    }
}
