# Event Booking API

**Stack:** Laravel 13 · PHP 8.4 · PostgreSQL 18 · Nginx 1.30 · Sanctum · database queue · PHPUnit · Docker Compose

## Run

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose restart queue    # the worker needs vendor/ and the jobs table
```

API: **http://localhost:8080/api** (port: `APP_PORT`). Services: `app` (PHP-FPM), `queue` (worker), `nginx`, `db` (databases `event_booking` and `testing`).

## Test data and tokens

The seeder creates users `alice@example.com`, `bob@example.com`, `charlie@example.com` (password `password`) and events covering all cases: a free event, a full one (`Single Seat Masterclass`), one with a cancelled booking, a past one and 20 random events for pagination. Reset with `php artisan migrate:fresh --seed`.

```bash
docker compose exec app php artisan app:issue-token alice@example.com
curl -H "Authorization: Bearer <token>" -H "Accept: application/json" http://localhost:8080/api/events
```

## Endpoints

All endpoints require a token (`401` otherwise). Dates are stored and returned in UTC (ISO 8601). Lists are paginated, 15 items per page.

| Method | Endpoint | Responses |
|---|---|---|
| `GET` | `/api/events` | `200` upcoming events with `available_seats` |
| `POST` | `/api/events/{event}/bookings` | `201` created · `200` existing active booking · `404` · `409` full or started |
| `GET` | `/api/bookings` | `200` own bookings, active and cancelled |
| `DELETE` | `/api/bookings/{booking}` | `204` cancelled or already cancelled · `403` not yours · `404` · `409` active and event started |

## Queue and emails

The worker runs in the `queue` container (`queue:work --tries=3 --timeout=60`). The job has 3 tries, 10 s / 60 s backoff and a 30 s timeout (below `retry_after` = 90 s). Failed jobs go to `failed_jobs`.

```bash
docker compose logs -f queue                                              # watch jobs
docker compose exec app php artisan queue:work --tries=3 --timeout=60     # manual worker, after `docker compose stop queue`
docker compose exec app sh -c 'tail -n 60 storage/logs/laravel-*.log'     # emails (log mailer, daily log files)
```

The email contains the event name, the start time (UTC) and the booking ID.

## Tests

```bash
docker compose exec app php artisan test
```

Tests run on a separate PostgreSQL database `testing` (the solution relies on a partial unique index and `SELECT ... FOR UPDATE`). No real emails are sent: `Mail::fake()`, `Queue::fake()` and the `array` mailer are used. `ConcurrentBookingTest` starts real parallel processes and takes about 25 s.

## Design decisions

- Thin controllers. State changes live in `BookingService` / `BookingCancellationService`, read queries in `EventQuery` / `BookingQuery`.
- `available_seats` is not stored. It is calculated as `capacity - active bookings` (`withCount`), so there is no counter to get out of sync.
- Cancellation sets `status = cancelled` and `cancelled_at`. History is kept, and rebooking creates a new row.
- Database constraints: partial unique index `(user_id, event_id) WHERE status = 'active'`, `CHECK` on capacity and status, foreign keys without cascade delete.
- `409` errors are exceptions extending `ConflictHttpException`. `403` comes from `BookingPolicy`, `404` from route model binding (`{"message": "Not Found."}`). `201` vs `200` is decided by `wasRecentlyCreated`.

## Concurrency

Booking and cancellation run in a transaction that first locks the event row (`SELECT ... FOR UPDATE`). All writes for one event are serialized, so two requests cannot both take the last seat. Different events do not block each other. Both operations take the same lock first, so they cannot deadlock. Cancellation re-reads the booking after locking, so repeated cancellations release the seat only once. The partial unique index is a second line of defence against duplicate active bookings. The email job is dispatched `afterCommit()`, and the worker reloads the booking and skips the email if it has been cancelled.

## Known limitations

- The Docker setup is for development only (bind mount, PHP-FPM runs as root).
- Tokens do not expire and can only be issued with the Artisan command.
- Fixed page size and no rate limiting.
- Emails are written to the log only (set `MAIL_*` for a real SMTP server).

## Time spent

About 3.5 hours. All requirements are implemented.
