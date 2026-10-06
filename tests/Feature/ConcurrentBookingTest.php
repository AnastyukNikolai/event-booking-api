<?php

namespace Tests\Feature;

use App\Exceptions\EventFullException;
use App\Models\Event;
use App\Models\User;
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

    private function bookSimultaneously(array $userIds, int $eventId): array
    {
        $startAt = microtime(true) + 5;
        $tasks = [];

        foreach ($userIds as $userId) {
            $tasks[] = static function () use ($userId, $eventId, $startAt) {
                usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));

                try {
                    $booking = app(BookingService::class)->book(User::findOrFail($userId), Event::findOrFail($eventId));

                    return $booking->wasRecentlyCreated ? 'created' : 'existing';
                } catch (EventFullException) {
                    return 'full';
                }
            };
        }

        return Concurrency::run($tasks);
    }

    private function countResults(array $results): array
    {
        $counts = array_count_values($results);
        ksort($counts);

        return $counts;
    }
}
