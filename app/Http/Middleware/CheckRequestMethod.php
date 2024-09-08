<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckRequestMethod
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  array  ...$methods  (list of allowed methods)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$methods)
    {
        // Log the request method to confirm if middleware is running
        Log::info('CheckRequestMethod Middleware running, Request Method: ' . $request->method());

        if (!in_array($request->getMethod(), $methods)) {
            return response()->json([
                'success' => false,
                'message' => 'Method Not Allowed',
                'allowed_methods' => $methods
            ], 405);
        }

        return $next($request);
    }
}
