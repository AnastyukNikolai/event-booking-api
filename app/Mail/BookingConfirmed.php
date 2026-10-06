<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingConfirmed extends Mailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking #{$this->booking->id} confirmed: {$this->booking->event->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking-confirmed',
            with: [
                'event' => $this->booking->event,
                'startsAt' => $this->booking->event->starts_at->utc()->format('Y-m-d H:i').' UTC',
            ],
        );
    }
}
