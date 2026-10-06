<?php

namespace App\Queries;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EventQuery
{
    public function upcoming(int $perPage = 15): LengthAwarePaginator
    {
        return Event::query()
            ->where('starts_at', '>', now())
            ->withCount('activeBookings')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate($perPage);
    }
}
