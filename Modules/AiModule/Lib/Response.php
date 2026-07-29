<?php

use Illuminate\Http\JsonResponse;

if (!function_exists('successResponse')) {
    function successResponse(mixed $data = [], string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }
}

if (!function_exists('errorResponse'))
{
    function errorResponse(string $message = 'Error', int $status = 500, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $status);
    }
}
