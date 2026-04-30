<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Http\Resources\SchoolClassResource;

class SchoolClassController extends Controller
{
    public function index()
    {
        $data = SchoolClass::all();

        return SchoolClassResource::collection($data);
    }
}
