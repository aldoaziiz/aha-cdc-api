<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolEducation;
use App\Http\Resources\SchoolEducationResource;

class SchoolEducationController extends Controller
{
    public function index()
    {
        $data = SchoolEducation::all();

        return SchoolEducationResource::collection($data);
    }
}
