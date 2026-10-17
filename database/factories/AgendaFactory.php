<?php

namespace Database\Factories;

use App\Models\Agenda;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agenda> */
class AgendaFactory extends Factory
{
    public function definition(): array
    {
        return ['meeting_id' => Meeting::factory(), 'title' => 'Agenda diskusi', 'description' => fake()->paragraph()];
    }
}
