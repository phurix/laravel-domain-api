<?php

namespace Deaduseful\DomainApi;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiToken
{
    public function handle(Request $request, Closure $next): JsonResponse
    {
        if ($request->bearerToken()) {
            $token = $request->bearerToken();
        } elseif ($request->input('api_token')) {
            $token = $request->input('api_token');
        } else {
            $token = null;
        }
        if ($token !== config(ServiceProvider::NAMESPACE . '.api.token')) {
            return response()->json('Unauthorized', 401);
        }
        return $next($request);
    }
}
