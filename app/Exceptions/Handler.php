<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        // Kalau request API
        if ($request->expectsJson()) {

            // 401 Unauthorized (belum login / token invalid)
            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // 422 Unprocessable Entity (validasi gagal)
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $exception->errors(),
                ], 422);
            }

            // 404 Not Found
            if ($exception instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => 'Resource not found.'
                ], 404);
            }

            // 405 Method Not Allowed
            if ($exception instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'message' => 'Method not allowed.'
                ], 405);
            }

            // Default: 500 Internal Server Error
            return response()->json([
                'message' => $exception->getMessage() ?: 'Server Error',
            ], 500);
        }

        // fallback ke default Laravel
        return parent::render($request, $exception);
    }
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'message' => 'Unauthenticated.'
        ], 401);
    }
}
