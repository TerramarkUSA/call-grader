<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasAccount
{
    /**
     * Ensure user has at least one account (office) assigned
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // System admin doesn't need an account
        if ($request->user()->role === 'system_admin') {
            return $next($request);
        }

        // Check if user has at least one account
        if ($request->user()->accounts()->count() === 0) {
            return response()->view('manager.calls.no-account', [], 403);
        }

        return $next($request);
    }
}
