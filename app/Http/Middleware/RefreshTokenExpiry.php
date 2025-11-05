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

            $remember = in_array('remember', $token->abilities ?? []);
            $maxExpiry = $remember ? $token->created_at->addDays(30) : $token->created_at->addHours(8);

            $newExpiry = now()->addHours(2);
            if ($newExpiry->gt($maxExpiry)) {
                $newExpiry = $maxExpiry;
            }

            $token->expires_at = $newExpiry;
            $token->save();
        }

        return $response;
    }
}
