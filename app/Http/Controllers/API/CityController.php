<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\City;
use App\Http\Resources\CityResource;

class CityController extends Controller
{
    public function index()
    {
        $data = City::orderBy('name', 'asc')->get();

        return CityResource::collection($data);
    }
}
