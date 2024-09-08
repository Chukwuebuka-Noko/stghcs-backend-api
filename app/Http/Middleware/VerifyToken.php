<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class VerifyToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, ...$guards)
    {
        if ($request->expectsJson()) {
            try {
                // Get the authenticated user
                $user = JWTAuth::parseToken()->authenticate();
            } catch (TokenExpiredException $e) {
                return response()->json([
                    'status' => 401,
                    'response' => 'Unauthorized',
                    'message' => 'Token has expired'
                ], 401);
            } catch (TokenInvalidException $e) {
                return response()->json([
                    'status' => 401,
                    'response' => 'Unauthorized',
                    'message' => 'Token is invalid'
                ], 401);
            } catch (JWTException $e) {
                return response()->json([
                    'status' => 401,
                    'response' => 'Unauthorized',
                    'message' => 'Token not provided'
                ], 401);
            }
        } else {
            $this->authenticate($request, $guards);
        }

        return $next($request);
    }
}
