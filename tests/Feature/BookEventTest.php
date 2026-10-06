<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookEventTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_user_can_book_an_available_seat(): void
    {
        $event = Event::factory()->create([
            'name' => 'Laravel Conference',
            'starts_at' => '2030-05-10 09:30:00',
            'capacity' => 10,
        ]);

        $response = $this->postJson("/api/events/{$event->id}/bookings")
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.event.name', 'Laravel Conference')
            ->assertJsonPath('data.event.starts_at', '2030-05-10T09:30:00Z')
            ->assertJsonPath('data.event.capacity', 10)
            ->assertJsonPath('data.created_at', now()->toIso8601ZuluString())
            ->assertJsonPath('data.cancelled_at', null);

        $this->assertDatabaseHas('bookings', [
            'id' => $response->json('data.id'),
            'user_id' => $this->user->id,
            'event_id' => $event->id,
            'status' => BookingStatus::Active->value,
        ]);
    }

    public function test_booking_takes_a_seat(): void
    {
        $event = Event::factory()->create(['capacity' => 3]);

        $this->postJson("/api/events/{$event->id}/bookings")->assertCreated();

        $this->getJson('/api/events')
            ->assertJsonPath('data.0.available_seats', 2);
    }

    public function test_booking_the_last_seat_is_allowed(): void
    {
        $event = Event::factory()->create(['capacity' => 2]);
        Booking::factory()->for($event)->create();

        $this->postJson("/api/events/{$event->id}/bookings")->assertCreated();

        $this->assertSame(2, $event->activeBookings()->count());
    }

    public function test_booking_a_full_event_is_rejected(): void
    {
        $event = Event::factory()->create(['capacity' => 2]);
        Booking::factory()->count(2)->for($event)->create();

        $this->postJson("/api/events/{$event->id}/bookings")
            ->assertConflict()
            ->assertJson(['message' => 'No seats are available for this event.']);

        $this->assertSame(2, $event->bookings()->count());
    }

    public function test_cancelled_bookings_do_not_occupy_seats(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);
        Booking::factory()->cancelled()->for($event)->create();

        $this->postJson("/api/events/{$event->id}/bookings")->assertCreated();
    }

    public function test_booking_a_started_event_is_rejected(): void
    {
        $event = Event::factory()->started()->create();

        $this->postJson("/api/events/{$event->id}/bookings")
            ->assertConflict()
            ->assertJson(['message' => 'The event has already started.']);

        $this->assertSame(0, Booking::count());
    }

    public function test_booking_an_event_starting_right_now_is_rejected(): void
    {
        $event = Event::factory()->create(['starts_at' => now()]);

        $this->postJson("/api/events/{$event->id}/bookings")->assertConflict();
    }

    public function test_booking_a_missing_event_returns_not_found(): void
    {
        $this->postJson('/api/events/999999/bookings')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found.']);
    }

    public function test_repeated_booking_returns_existing_active_booking(): void
    {
        $event = Event::factory()->create();

        $bookingId = $this->postJson("/api/events/{$event->id}/bookings")
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/events/{$event->id}/bookings")
            ->assertOk()
            ->assertJsonPath('data.id', $bookingId)
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(1, Booking::count());
    }

    public function test_existing_booking_is_returned_even_if_event_is_full(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);
        $booking = Booking::factory()->for($this->user)->for($event)->create();

        $this->postJson("/api/events/{$event->id}/bookings")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id);
    }

    public function test_existing_booking_is_returned_even_if_event_has_started(): void
    {
        $event = Event::factory()->started()->create();
        $booking = Booking::factory()->for($this->user)->for($event)->create();

        $this->postJson("/api/events/{$event->id}/bookings")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id);
    }

    public function test_booking_of_another_user_does_not_count_as_existing(): void
    {
        $event = Event::factory()->create(['capacity' => 2]);
        $othersBooking = Booking::factory()->for($event)->create();

        $response = $this->postJson("/api/events/{$event->id}/bookings")->assertCreated();

        $this->assertNotSame($othersBooking->id, $response->json('data.id'));
    }
}
