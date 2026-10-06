<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
        User::factory()->create(['name' => 'Charlie', 'email' => 'charlie@example.com']);

        $conference = Event::factory()->create([
            'name' => 'Laravel Conference',
            'starts_at' => now()->addWeeks(2)->setTime(9, 0),
            'capacity' => 100,
        ]);

        $workshop = Event::factory()->create([
            'name' => 'PostgreSQL Workshop',
            'starts_at' => now()->addDays(5)->setTime(14, 0),
            'capacity' => 2,
        ]);

        $masterclass = Event::factory()->create([
            'name' => 'Single Seat Masterclass',
            'starts_at' => now()->addDays(10)->setTime(18, 30),
            'capacity' => 1,
        ]);

        Event::factory()->create([
            'name' => 'Past Meetup',
            'starts_at' => now()->subDays(3)->setTime(19, 0),
            'capacity' => 30,
        ]);

        Event::factory(20)->create();

        Booking::factory()->for($bob)->for($masterclass)->create();
        Booking::factory()->for($bob)->for($workshop)->create();
        Booking::factory()->cancelled()->for($alice)->for($workshop)->create();
        Booking::factory()->for($alice)->for($conference)->create();
    }
}
