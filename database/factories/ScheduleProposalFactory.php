<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\ScheduleProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduleProposal> */
class ScheduleProposalFactory extends Factory
{
    public function definition(): array
    {
        return ['meeting_id' => Meeting::factory(), 'proposed_by' => fn (array $attributes): int => Meeting::findOrFail($attributes['meeting_id'])->creator_id,
            'proposed_party' => 'company', 'meeting_version' => 1, 'starts_at' => now()->addDays(3)->setTime(9, 0),
            'ends_at' => now()->addDays(3)->setTime(10, 0), 'status' => 'pending', 'message' => 'Ajuan jadwal rapat'];
    }
}
