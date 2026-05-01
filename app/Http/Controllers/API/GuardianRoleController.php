<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GuardianRole;
use App\Http\Resources\GuardianRoleResource;

class GuardianRoleController extends Controller
{
    public function index()
    {
        $data = GuardianRole::orderBy('id', 'asc')->get();

        return GuardianRoleResource::collection($data);
    }
}
