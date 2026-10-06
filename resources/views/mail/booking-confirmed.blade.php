<x-mail::message>
# Your booking is confirmed

<x-mail::panel>
**Event:** {{ $event->name }}<br>
**Starts at:** {{ $startsAt }}<br>
**Booking ID:** {{ $booking->id }}
</x-mail::panel>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
