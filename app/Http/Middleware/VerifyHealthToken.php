<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyHealthToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('health.token', '');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['code' => 'HEALTH_UNAUTHORIZED', 'message' => 'Health check não autorizado.'], 401);
        }

        return $next($request);
    }
}
