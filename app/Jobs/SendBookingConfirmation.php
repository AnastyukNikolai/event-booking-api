<?php

namespace App\Jobs;

use App\Mail\BookingConfirmed;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Mail;

#[Tries(3)]
#[Backoff(10, 60)]
#[Timeout(30)]
class SendBookingConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function handle(): void
    {
        if (! $this->booking->isActive()) {
            return;
        }

        Mail::to($this->booking->user)->send(new BookingConfirmed($this->booking));
    }
}
