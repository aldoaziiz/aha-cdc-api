<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Clinic;
use App\Http\Resources\ClinicResource;

class ClinicController extends Controller
{
    public function index()
    {
        $data = Clinic::all();

        return ClinicResource::collection($data);
    }
}
