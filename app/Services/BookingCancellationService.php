<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingCannotBeCancelledException;
use App\Models\Booking;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class BookingCancellationService
{
    public function cancel(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $event = Event::query()->lockForUpdate()->findOrFail($booking->event_id);

            $booking->refresh();

            if (! $booking->isActive()) {
                return;
            }

            if ($event->hasStarted()) {
                throw new BookingCannotBeCancelledException;
            }

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });
    }
}
