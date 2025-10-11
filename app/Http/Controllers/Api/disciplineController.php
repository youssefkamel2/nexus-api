<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\discipline;

class disciplineController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        $disciplines = discipline::where('is_active', true)->get();
        return $this->success($disciplines, 'Disciplines retrieved successfully');
    }
}
