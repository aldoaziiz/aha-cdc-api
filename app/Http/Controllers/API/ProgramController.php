<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Program;
use App\Http\Resources\ProgramResource;

class ProgramController extends Controller
{
    public function index()
    {
        $data = Program::orderBy('id', 'asc')->get();

        return ProgramResource::collection($data);
    }
}
