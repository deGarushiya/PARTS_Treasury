<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $userRole = $request->user()->role ?? 'staff';

        if (!in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'Unauthorized. This action requires one of the following roles: ' . implode(', ', $roles)
            ], 403);
        }

        return $next($request);
    }
}

