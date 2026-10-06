<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_have_two_active_bookings_for_same_event(): void
    {
        $booking = Booking::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Booking::factory()->for($booking->user)->for($booking->event)->create();
    }

    public function test_cancelled_bookings_do_not_block_new_active_booking(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        Booking::factory()->cancelled()->for($user)->for($event)->create();
        Booking::factory()->cancelled()->for($user)->for($event)->create();
        Booking::factory()->for($user)->for($event)->create();

        $this->assertSame(3, $event->bookings()->count());
        $this->assertSame(1, $event->activeBookings()->count());
    }

    public function test_event_capacity_must_be_positive(): void
    {
        $this->expectException(QueryException::class);

        Event::factory()->create(['capacity' => 0]);
    }

    public function test_booking_status_is_restricted_to_known_values(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('bookings')->insert([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => 'pending',
        ]);
    }
}
