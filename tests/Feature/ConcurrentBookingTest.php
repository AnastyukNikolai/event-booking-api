<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\EventFullException;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Tests\TestCase;

class ConcurrentBookingTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beforeApplicationDestroyed(fn () => $this->truncateTablesForAllConnections());
    }

    public function test_concurrent_bookings_never_exceed_capacity(): void
    {
        $event = Event::factory()->create(['capacity' => 3]);
        $userIds = User::factory()->count(8)->create()->modelKeys();

        $results = $this->bookSimultaneously($userIds, $event->id);

        $this->assertSame(3, $event->activeBookings()->count());
        $this->assertSame(['created' => 3, 'full' => 5], $this->countResults($results));
    }

    public function test_concurrent_requests_of_one_user_create_a_single_booking(): void
    {
        $event = Event::factory()->create(['capacity' => 10]);
        $userId = User::factory()->create()->id;

        $results = $this->bookSimultaneously(array_fill(0, 5, $userId), $event->id);

        $this->assertSame(1, $event->bookings()->count());
        $this->assertSame(['created' => 1, 'existing' => 4], $this->countResults($results));
    }

    public function test_concurrent_repeated_cancellations_release_the_seat_once(): void
    {
        $event = Event::factory()->create(['capacity' => 2]);
        Booking::factory()->for($event)->create();
        $booking = Booking::factory()->for($event)->create();

        $cancel = static function () use ($booking) {
            app(BookingCancellationService::class)->cancel(Booking::findOrFail($booking->id));

            return 'cancelled';
        };

        Concurrency::run($this->startingTogether(array_fill(0, 5, $cancel)));

        $this->assertSame(1, $event->activeBookings()->count());
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_concurrent_cancellation_and_bookings_keep_capacity_consistent(): void
    {
        $event = Event::factory()->create(['capacity' => 2]);
        Booking::factory()->for($event)->create();
        $booking = Booking::factory()->for($event)->create();
        $userIds = User::factory()->count(4)->create()->modelKeys();

        $tasks = $this->bookingTasks($userIds, $event->id);
        $tasks[] = static function () use ($booking) {
            app(BookingCancellationService::class)->cancel(Booking::findOrFail($booking->id));

            return 'cancelled';
        };

        $results = Concurrency::run($this->startingTogether($tasks));
        $created = count(array_keys($results, 'created'));

        $this->assertLessThanOrEqual(1, $created);
        $this->assertSame(1 + $created, $event->activeBookings()->count());
        $this->assertLessThanOrEqual($event->capacity, $event->activeBookings()->count());
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    private function bookSimultaneously(array $userIds, int $eventId): array
    {
        return Concurrency::run($this->startingTogether($this->bookingTasks($userIds, $eventId)));
    }

    private function bookingTasks(array $userIds, int $eventId): array
    {
        $tasks = [];

        foreach ($userIds as $userId) {
            $tasks[] = static function () use ($userId, $eventId) {
                try {
                    $booking = app(BookingService::class)->book(User::findOrFail($userId), Event::findOrFail($eventId));

                    return $booking->wasRecentlyCreated ? 'created' : 'existing';
                } catch (EventFullException) {
                    return 'full';
                }
            };
        }

        return $tasks;
    }

    private function startingTogether(array $tasks): array
    {
        $startAt = microtime(true) + 5;
        $synchronized = [];

        foreach ($tasks as $task) {
            $synchronized[] = static function () use ($task, $startAt) {
                usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));

                return $task();
            };
        }

        return $synchronized;
    }

    private function countResults(array $results): array
    {
        $counts = array_count_values($results);
        ksort($counts);

        return $counts;
    }
}
