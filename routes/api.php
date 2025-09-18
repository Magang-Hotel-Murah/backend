<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReservationController;
use Illuminate\Container\Attributes\Auth;
use App\Http\Controllers\MeetingRoomReservationController;
use App\Http\Controllers\MeetingRoomController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed'])->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/meeting-room/{reservation}/status', [MeetingRoomReservationController::class, 'updateStatus']);
    });

    Route::get('/hotels/by-city', [HotelController::class, 'getHotelsByCity']);
    Route::get('/hotels/by-hotels', [HotelController::class, 'getHotelsById']);
    Route::get('/hotels/hotel-offers', [HotelController::class, 'getMultiHotelOffers']);
    Route::get('/hotels/hotel-offers/{offerId}', [HotelController::class, 'getOfferPricing']);

    Route::apiResource('reservations', ReservationController::class);
    Route::apiResource('transactions', TransactionController::class);

    Route::post('/meeting-room/reserve', [MeetingRoomReservationController::class, 'store']);
    Route::get('/meeting-room/reservations', [MeetingRoomReservationController::class, 'index']);
    Route::get('/meeting-room/{id}/reservations', [MeetingRoomReservationController::class, 'show']);

    Route::apiResource('meeting-room', MeetingRoomController::class);
});
