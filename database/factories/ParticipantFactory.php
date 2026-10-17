<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Participant> */
class ParticipantFactory extends Factory
{
    public function definition(): array
    {
        return ['meeting_id' => Meeting::factory(), 'user_id' => User::factory(), 'attendance_status' => 'pending', 'reminder_preference' => 'day_before'];
    }
}
