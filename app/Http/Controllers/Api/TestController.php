<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @OA\Get(
 *     path="/api/test",
 *     summary="Simple test endpoint",
 *     tags={"Test"},
 *     @OA\Response(response=200, description="Success")
 * )
 */
class TestController extends Controller
{
    public function test()
    {
        return response()->json(['message' => 'Test endpoint']);
    }
}
