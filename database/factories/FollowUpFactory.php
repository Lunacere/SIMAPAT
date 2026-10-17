<?php

namespace Database\Factories;

use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FollowUp> */
class FollowUpFactory extends Factory
{
    public function definition(): array
    {
        return ['meeting_id' => Meeting::factory(), 'user_id' => User::factory(), 'task' => fake()->sentence(), 'deadline' => today()->addWeek(), 'status' => 'pending'];
    }
}
