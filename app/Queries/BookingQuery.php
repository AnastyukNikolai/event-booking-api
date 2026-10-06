<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookingQuery
{
    public function forUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->bookings()
            ->with('event')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
