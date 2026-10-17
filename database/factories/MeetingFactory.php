<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Meeting> */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        return ['creator_id' => User::factory()->state(['role' => 'operator']), 'title' => fake()->sentence(3), 'starts_at' => now()->addDays(2)->setTime(9, 0), 'ends_at' => now()->addDays(2)->setTime(10, 0), 'location' => 'Ruang rapat', 'status' => 'scheduled', 'version' => 1];
    }
}
