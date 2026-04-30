<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payer;
use App\Http\Resources\PayerResource;

class PayerController extends Controller
{
    public function index()
    {
        $data = Payer::all();

        return PayerResource::collection($data);
    }
}
