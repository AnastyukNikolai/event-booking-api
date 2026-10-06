<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Event;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request, Event $event, BookingService $bookings): BookingResource
    {
        return new BookingResource($bookings->book($request->user(), $event));
    }
}
