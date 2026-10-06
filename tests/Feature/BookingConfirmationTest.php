<?php

namespace Tests\Feature;

use App\Jobs\SendBookingConfirmation;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Services\BookingCancellationService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class BookingConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_new_booking_queues_confirmation_after_commit(): void
    {
        Queue::fake();
        $event = Event::factory()->create();

        $bookingId = $this->postJson("/api/events/{$event->id}/bookings")->assertCreated()->json('data.id');

        Queue::assertPushedTimes(SendBookingConfirmation::class, 1);
        Queue::assertPushed(
            SendBookingConfirmation::class,
            fn (SendBookingConfirmation $job) => $job->booking->id === $bookingId && $job->afterCommit === true,
        );
    }

    public function test_repeated_booking_request_does_not_queue_another_confirmation(): void
    {
        Queue::fake();
        $event = Event::factory()->create();

        $this->postJson("/api/events/{$event->id}/bookings")->assertCreated();
        $this->postJson("/api/events/{$event->id}/bookings")->assertOk();
        $this->postJson("/api/events/{$event->id}/bookings")->assertOk();

        Queue::assertPushedTimes(SendBookingConfirmation::class, 1);
    }

    public function test_rejected_booking_does_not_queue_confirmation(): void
    {
        Queue::fake();
        $full = Event::factory()->create(['capacity' => 1]);
        Booking::factory()->for($full)->create();
        $started = Event::factory()->started()->create();

        $this->postJson("/api/events/{$full->id}/bookings")->assertConflict();
        $this->postJson("/api/events/{$started->id}/bookings")->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_confirmation_email_is_sent_when_queued_job_is_processed(): void
    {
        Mail::fake();
        $event = Event::factory()->create();

        $bookingId = $this->postJson("/api/events/{$event->id}/bookings")->assertCreated()->json('data.id');

        Mail::assertSent(BookingConfirmed::class, 1);
        Mail::assertSent(
            BookingConfirmed::class,
            fn (BookingConfirmed $mail) => $mail->hasTo($this->user->email) && $mail->booking->id === $bookingId,
        );
    }

    public function test_confirmation_is_not_sent_when_transaction_is_rolled_back(): void
    {
        Mail::fake();
        $event = Event::factory()->create();

        try {
            DB::transaction(function () use ($event) {
                app(BookingService::class)->book($this->user, $event);

                throw new RuntimeException('Rollback');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, Booking::count());
        Mail::assertNothingSent();
    }

    public function test_cancelled_booking_is_skipped_when_queued_notification_is_processed(): void
    {
        Mail::fake();
        $booking = Booking::factory()->for($this->user)->create();
        $queuedPayload = serialize(new SendBookingConfirmation($booking));

        app(BookingCancellationService::class)->cancel($booking);
        unserialize($queuedPayload)->handle();

        Mail::assertNothingSent();
    }

    public function test_active_booking_is_emailed_when_queued_notification_is_processed(): void
    {
        Mail::fake();
        $booking = Booking::factory()->for($this->user)->create();

        unserialize(serialize(new SendBookingConfirmation($booking)))->handle();

        Mail::assertSent(BookingConfirmed::class, fn (BookingConfirmed $mail) => $mail->hasTo($this->user->email));
    }

    public function test_confirmation_email_contains_event_name_start_time_and_booking_id(): void
    {
        $event = Event::factory()->create([
            'name' => 'Laravel Conference',
            'starts_at' => '2030-05-10 09:30:00',
        ]);
        $booking = Booking::factory()->for($this->user)->for($event)->create();

        $mail = new BookingConfirmed($booking);

        $mail->assertHasSubject("Booking #{$booking->id} confirmed: Laravel Conference");
        $mail->assertSeeInHtml('Laravel Conference');
        $mail->assertSeeInHtml('2030-05-10 09:30 UTC');
        $mail->assertSeeInText("Booking ID:** {$booking->id}");
    }

    public function test_queued_job_payload_has_retry_and_timeout_settings(): void
    {
        config(['queue.default' => 'database']);
        $event = Event::factory()->create();

        $this->postJson("/api/events/{$event->id}/bookings")->assertCreated();

        $payload = json_decode(DB::table('jobs')->sole()->payload, true);

        $this->assertSame(SendBookingConfirmation::class, $payload['displayName']);
        $this->assertSame(3, $payload['maxTries']);
        $this->assertSame('10,60', $payload['backoff']);
        $this->assertSame(30, $payload['timeout']);
    }
}
