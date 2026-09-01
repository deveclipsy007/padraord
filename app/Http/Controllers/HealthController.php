<?php

namespace App\Http\Controllers;

use App\Services\SystemHealth;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(SystemHealth $health): JsonResponse
    {
        $payload = $health->check();
        $status = $payload['status'] === 'ok' ? 200 : 503;

        return response()->json($payload, $status);
    }
}
