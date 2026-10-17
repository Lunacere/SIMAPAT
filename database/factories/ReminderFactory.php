<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reminder> */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return ['meeting_id' => Meeting::factory(), 'user_id' => User::factory(), 'meeting_version' => 1, 'scheduled_at' => now(), 'status' => 'pending'];
    }
}
