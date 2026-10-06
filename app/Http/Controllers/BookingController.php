<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Event;
use App\Queries\BookingQuery;
use App\Services\BookingCancellationService;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookingController extends Controller
{
    public function index(Request $request, BookingQuery $bookings): AnonymousResourceCollection
    {
        return BookingResource::collection($bookings->forUser($request->user()));
    }

    public function store(Request $request, Event $event, BookingService $bookings): BookingResource
    {
        return new BookingResource($bookings->book($request->user(), $event));
    }

    public function destroy(Booking $booking, BookingCancellationService $cancellation): Response
    {
        $cancellation->cancel($booking);

        return response()->noContent();
    }
}
