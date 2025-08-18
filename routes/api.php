<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReservationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed'])->name('verification.verify');
    Route::apiResource('reservations', ReservationController::class);
    Route::apiResource('transactions', TransactionController::class);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/hotels/search', [HotelController::class, 'searchHotels']);
    Route::get('/hotels/by-hotels', [HotelController::class, 'getHotelsByIds']);
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
    });
});
