<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckDevAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Restrict access to localhost only
        if (!in_array($request->ip(), ['127.0.0.1', '::1'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        return $next($request);
    }
}
