<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RefreshTokenExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token && $token->expires_at) {
            $expiresInMinutes = now()->diffInMinutes($token->expires_at, false);

            if ($expiresInMinutes < 30) {
                $token->expires_at = now()->addHours(2);
                $token->save();
            }
        }

        return $response;
    }
}
