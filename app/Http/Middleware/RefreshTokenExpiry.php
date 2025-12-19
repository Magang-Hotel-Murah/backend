<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RefreshTokenExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $token = $user->currentAccessToken();

            if ($token && $token->expires_at) {
                $hasRemember = in_array('remember', $token->abilities ?? []);

                if ($hasRemember) {
                    Log::info('Skipping refresh for remember token', [
                        'token_id' => $token->id
                    ]);
                } else {
                    $expiresInMinutes = now()->diffInMinutes($token->expires_at, false);

                    if ($expiresInMinutes < 60) {
                        $token->expires_at = now()->addHours(8);
                        $token->save();

                        Log::info('Token expiry refreshed', [
                            'token_id' => $token->id,
                            'new_expiry' => $token->expires_at
                        ]);
                    }
                }
            }
        }

        return $next($request);
    }
}
