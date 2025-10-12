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
        $disciplines = Discipline::where('is_active', true)->get();
        return $this->success($disciplines, 'Disciplines retrieved successfully');
    }
}
