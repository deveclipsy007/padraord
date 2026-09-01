<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = (string) $request->header('X-Request-Id', '');
        $requestId = preg_match('/^[A-Za-z0-9._-]{8,100}$/', $candidate) ? $candidate : (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
        Log::withContext([
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        $response = $next($request);
        Log::withContext([
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
        ]);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
