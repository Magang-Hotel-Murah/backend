<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        if (!$bearer) {
            return response()->json(['message' => 'Missing token.'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($bearer);
        if (!$accessToken) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        if (is_null($accessToken->expires_at) || $accessToken->expires_at->isPast()) {
            $accessToken->delete();
            return response()->json(['message' => 'Session expired. Please login again.'], 401);
        }

        return $next($request);
    }
}
