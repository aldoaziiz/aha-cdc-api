<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\School;
use App\Http\Resources\SchoolResource;

class SchoolController extends Controller
{
    public function index()
    {
        $data = School::orderBy('name', 'asc')->get();

        return SchoolResource::collection($data);
    }
}
