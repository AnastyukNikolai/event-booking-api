<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_lists_only_upcoming_events_ordered_by_start_time(): void
    {
        $later = Event::factory()->create(['starts_at' => now()->addDays(2)]);
        $sooner = Event::factory()->create(['starts_at' => now()->addMinute()]);
        Event::factory()->create(['starts_at' => now()->subDay()]);
        Event::factory()->create(['starts_at' => now()]);

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $later->id);
    }

    public function test_event_contains_required_fields_with_utc_dates(): void
    {
        $event = Event::factory()->create([
            'name' => 'Laravel Conference',
            'starts_at' => '2030-05-10 09:30:00',
            'capacity' => 50,
        ]);

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('data.0', [
                'id' => $event->id,
                'name' => 'Laravel Conference',
                'starts_at' => '2030-05-10T09:30:00Z',
                'capacity' => 50,
                'available_seats' => 50,
            ]);
    }

    public function test_available_seats_count_only_active_bookings_of_the_event(): void
    {
        $event = Event::factory()->create(['capacity' => 5]);
        $otherEvent = Event::factory()->create(['capacity' => 10]);
        Booking::factory()->count(2)->for($event)->create();
        Booking::factory()->cancelled()->for($event)->create();
        Booking::factory()->count(3)->for($otherEvent)->create();

        $events = $this->getJson('/api/events')
            ->assertOk()
            ->collect('data')
            ->keyBy('id');

        $this->assertSame(3, $events[$event->id]['available_seats']);
        $this->assertSame(7, $events[$otherEvent->id]['available_seats']);
    }

    public function test_full_event_has_no_available_seats(): void
    {
        $event = Event::factory()->create(['capacity' => 2]);
        Booking::factory()->count(2)->for($event)->create();

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonPath('data.0.available_seats', 0);
    }

    public function test_events_are_paginated(): void
    {
        Event::factory()->count(20)->create();

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/events?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }
}
