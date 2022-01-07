<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoffeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['brew']]);
    }

    public function brew(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Coffee brewing unavailable at this time. Would you like some tea instead?'], 418);
    }
}
