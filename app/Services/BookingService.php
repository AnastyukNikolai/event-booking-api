<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\EventAlreadyStartedException;
use App\Exceptions\EventFullException;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function book(User $user, Event $event): Booking
    {
        return DB::transaction(function () use ($user, $event) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);

            $booking = $user->bookings()
                ->whereBelongsTo($event)
                ->where('status', BookingStatus::Active)
                ->first();

            if (! $booking) {
                if ($event->hasStarted()) {
                    throw new EventAlreadyStartedException;
                }

                if ($event->activeBookings()->count() >= $event->capacity) {
                    throw new EventFullException;
                }

                $booking = $user->bookings()->create([
                    'event_id' => $event->id,
                    'status' => BookingStatus::Active,
                ]);
            }

            return $booking->setRelation('event', $event);
        });
    }
}
