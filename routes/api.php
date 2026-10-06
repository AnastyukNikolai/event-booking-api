<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/events', [EventController::class, 'index']);
    Route::post('/events/{event}/bookings', [BookingController::class, 'store']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->can('cancel', 'booking');
});
