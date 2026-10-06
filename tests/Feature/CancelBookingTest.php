<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelBookingTest extends TestCase
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

    public function test_user_can_cancel_own_booking_and_it_is_kept_in_database(): void
    {
        $booking = Booking::factory()->for($this->user)->create();

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    }

    public function test_cancelling_releases_the_seat(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);
        $booking = Booking::factory()->for($this->user)->for($event)->create();

        $this->getJson('/api/events')->assertJsonPath('data.0.available_seats', 0);

        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();

        $this->getJson('/api/events')->assertJsonPath('data.0.available_seats', 1);
    }

    public function test_repeated_cancellation_does_not_release_another_seat(): void
    {
        $event = Event::factory()->create(['capacity' => 3]);
        Booking::factory()->for($event)->create();
        $booking = Booking::factory()->for($this->user)->for($event)->create();

        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();
        $cancelledAt = $booking->fresh()->cancelled_at;

        $this->travel(5)->minutes();

        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();

        $this->getJson('/api/events')->assertJsonPath('data.0.available_seats', 2);
        $this->assertSame(1, $event->activeBookings()->count());
        $this->assertEquals($cancelledAt, $booking->fresh()->cancelled_at);
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertForbidden();

        $this->assertTrue($booking->fresh()->isActive());
    }

    public function test_cancelling_a_missing_booking_returns_not_found(): void
    {
        $this->deleteJson('/api/bookings/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found.']);
    }

    public function test_active_booking_cannot_be_cancelled_after_event_starts(): void
    {
        $event = Event::factory()->started()->create();
        $booking = Booking::factory()->for($this->user)->for($event)->create();

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertConflict()
            ->assertJson(['message' => 'The booking cannot be cancelled because the event has already started.']);

        $this->assertTrue($booking->fresh()->isActive());
    }

    public function test_already_cancelled_booking_returns_no_content_even_after_event_starts(): void
    {
        $event = Event::factory()->started()->create();
        $booking = Booking::factory()->cancelled()->for($this->user)->for($event)->create();

        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();
    }

    public function test_user_can_book_again_after_cancellation_creating_a_new_booking(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);

        $firstId = $this->postJson("/api/events/{$event->id}/bookings")->assertCreated()->json('data.id');
        $this->deleteJson("/api/bookings/{$firstId}")->assertNoContent();

        $secondId = $this->postJson("/api/events/{$event->id}/bookings")
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(BookingStatus::Cancelled, Booking::find($firstId)->status);
        $this->assertSame(2, $event->bookings()->count());
        $this->assertSame(1, $event->activeBookings()->count());
    }

    public function test_seat_released_by_cancellation_can_be_taken_by_another_user(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);
        $booking = Booking::factory()->for($this->user)->for($event)->create();

        Sanctum::actingAs($other = User::factory()->create());
        $this->postJson("/api/events/{$event->id}/bookings")->assertConflict();

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();

        Sanctum::actingAs($other);
        $this->postJson("/api/events/{$event->id}/bookings")->assertCreated();
    }
}
