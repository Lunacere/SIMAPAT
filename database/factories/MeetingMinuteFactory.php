<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingMinute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeetingMinute> */
class MeetingMinuteFactory extends Factory
{
    public function definition(): array
    {
        return ['meeting_id' => Meeting::factory(), 'discussion' => fake()->paragraph(), 'decisions' => fake()->sentence(), 'recorded_on' => today()];
    }
}
