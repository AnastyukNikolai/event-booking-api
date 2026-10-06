<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_at' => $this->starts_at->toIso8601ZuluString(),
            'capacity' => $this->capacity,
            'available_seats' => $this->whenCounted(
                'activeBookings',
                fn (int $count) => max(0, $this->capacity - $count),
            ),
        ];
    }
}
