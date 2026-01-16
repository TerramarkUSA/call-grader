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
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // System admin can access everything
        if ($request->user()->role === 'system_admin') {
            return $next($request);
        }

        // Site admin can access site_admin and manager routes
        if ($request->user()->role === 'site_admin' && in_array('site_admin', $roles)) {
            return $next($request);
        }

        // Check if user has required role
        if (in_array($request->user()->role, $roles)) {
            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
