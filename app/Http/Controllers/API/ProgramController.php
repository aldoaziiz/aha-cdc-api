<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;

class ProgramController extends Controller
{
    public function index()
    {
        $data = Program::orderBy('id', 'asc')->get();

        return ProgramResource::collection($data);
    }
}
