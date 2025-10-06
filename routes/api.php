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
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\PositionController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed'])->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::put('/meeting-room/{id}/status', [MeetingRoomReservationController::class, 'updateStatus']);
        Route::apiResource('divisions', DivisionController::class, ['only' => ['store', 'update', 'destroy']]);
        Route::apiResource('positions', PositionController::class, ['only' => ['store', 'update', 'destroy']]);
        Route::apiResource('user-profiles', UserProfileController::class, ['only' => ['index']]);
        Route::apiResource('transactions', TransactionController::class);
    });

    Route::get('/hotels/by-city', [HotelController::class, 'getHotelsByCity']);
    Route::get('/hotels/by-hotels', [HotelController::class, 'getHotelsById']);
    Route::get('/hotels/hotel-offers', [HotelController::class, 'getMultiHotelOffers']);
    Route::get('/hotels/hotel-offers/{offerId}', [HotelController::class, 'getOfferPricing']);

    Route::apiResource('reservations', ReservationController::class);
    Route::apiResource('transactions', TransactionController::class, ['only' => ['show']]);



    Route::post('/meeting-room/reserve', [MeetingRoomReservationController::class, 'store']);
    Route::get('/meeting-room/reservations', [MeetingRoomReservationController::class, 'index']);
    Route::get('/meeting-room/{id}/reservations', [MeetingRoomReservationController::class, 'show']);
    Route::get('/meeting-room/reservations/{id}', [MeetingRoomReservationController::class, 'detail']);

    Route::apiResource('meeting-room', MeetingRoomController::class);
    Route::get('/user-profile/{id?}', [UserProfileController::class, 'show']);
    Route::apiResource('user-profiles', UserProfileController::class, ['only' => ['store', 'update']]);
    Route::apiResource('divisions', DivisionController::class, ['only' => ['index', 'show']]);
    Route::apiResource('positions', PositionController::class, ['only' => ['index', 'show']]);
});
