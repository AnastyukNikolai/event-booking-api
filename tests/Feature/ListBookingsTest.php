<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListBookingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_user_sees_only_own_bookings(): void
    {
        $own = Booking::factory()->for($this->user)->create();
        Booking::factory()->count(3)->create();

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_active_and_cancelled_bookings_are_listed_newest_first(): void
    {
        $event = Event::factory()->create();
        $cancelled = Booking::factory()->cancelled()->for($this->user)->for($event)->create();
        $active = Booking::factory()->for($this->user)->for($event)->create();
        $past = Booking::factory()->for($this->user)->for(Event::factory()->started())->create();

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $past->id)
            ->assertJsonPath('data.1.id', $active->id)
            ->assertJsonPath('data.1.status', 'active')
            ->assertJsonPath('data.2.id', $cancelled->id)
            ->assertJsonPath('data.2.status', 'cancelled');
    }

    public function test_booking_contains_event_details_status_and_creation_date(): void
    {
        $event = Event::factory()->create([
            'name' => 'Laravel Conference',
            'starts_at' => '2030-05-10 09:30:00',
            'capacity' => 50,
        ]);
        $booking = Booking::factory()->cancelled()->for($this->user)->for($event)->create([
            'created_at' => '2030-01-02 03:04:05',
            'cancelled_at' => '2030-01-03 04:05:06',
        ]);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonPath('data.0', [
                'id' => $booking->id,
                'status' => 'cancelled',
                'event' => [
                    'id' => $event->id,
                    'name' => 'Laravel Conference',
                    'starts_at' => '2030-05-10T09:30:00Z',
                    'capacity' => 50,
                ],
                'created_at' => '2030-01-02T03:04:05Z',
                'cancelled_at' => '2030-01-03T04:05:06Z',
            ]);
    }

    public function test_bookings_are_paginated(): void
    {
        Booking::factory()->count(17)->for($this->user)->create();

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 17)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/bookings?page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_without_bookings_gets_empty_list(): void
    {
        Booking::factory()->create();

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }
}
