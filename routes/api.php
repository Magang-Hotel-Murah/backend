<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\MeetingRoomReservationController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\MeetingRequestController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\InviteController;

Route::get('/meeting-display/{company_code}', [MeetingRoomReservationController::class, 'meetingDisplay']);
Route::post('/register/admin', [AuthController::class, 'registerAdmin']);
Route::post('/register/user', [AuthController::class, 'registerUser']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])->name('verification.verify');

Route::post('/activate-account', [InviteController::class, 'activate']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('role:company_admin,super_admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::put('/meeting-room-reservations/{id}/status', [MeetingRoomReservationController::class, 'updateStatus']);
        Route::apiResource('divisions', DivisionController::class, ['only' => ['store', 'update', 'destroy']]);
        Route::apiResource('positions', PositionController::class, ['only' => ['store', 'update', 'destroy']]);
        Route::apiResource('user-profiles', UserProfileController::class, ['only' => ['index']]);
        Route::apiResource('transactions', TransactionController::class);
        Route::apiResource('meeting-room', MeetingRoomController::class);
        Route::post('/invite-users', [InviteController::class, 'inviteUsers']);
    });

    Route::get('/hotels/by-city', [HotelController::class, 'getHotelsByCity']);
    Route::get('/hotels/by-hotels', [HotelController::class, 'getHotelsById']);
    Route::get('/hotels/hotel-offers', [HotelController::class, 'getMultiHotelOffers']);
    Route::get('/hotels/hotel-offers/{offerId}', [HotelController::class, 'getOfferPricing']);

    Route::apiResource('reservations', ReservationController::class);
    Route::apiResource('transactions', TransactionController::class, ['only' => ['show']]);

    Route::apiResource('/meeting-room-reservations', MeetingRoomReservationController::class);
    Route::get('/meeting-room/{room_id}/reservations', [MeetingRoomReservationController::class, 'indexByRoom']);

    Route::get('/user-profile/{id?}', [UserProfileController::class, 'show']);
    Route::apiResource('meeting-room', MeetingRoomController::class, ['only' => ['index', 'show']]);
    Route::apiResource('user-profiles', UserProfileController::class, ['only' => ['store', 'update']]);
    Route::apiResource('divisions', DivisionController::class, ['only' => ['index', 'show']]);
    Route::apiResource('positions', PositionController::class, ['only' => ['index', 'show']]);

    Route::apiResource('/meeting-requests', MeetingRequestController::class);
    Route::middleware('role:finance_officer')->put('/meeting-requests/{id}/status', [MeetingRequestController::class, 'updateStatus']);
});
