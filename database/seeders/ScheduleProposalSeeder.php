<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\ScheduleProposal;
use Illuminate\Database\Seeder;

class ScheduleProposalSeeder extends Seeder
{
    public function run(): void
    {
        Meeting::whereNotNull('client_id')->where('status', 'scheduled')->where('starts_at', '>', now())
            ->whereDoesntHave('scheduleProposals')->each(function (Meeting $meeting): void {
                ScheduleProposal::factory()->for($meeting)->create([
                    'proposed_by' => $meeting->creator_id, 'meeting_version' => $meeting->version,
                    'starts_at' => $meeting->starts_at, 'ends_at' => $meeting->ends_at,
                ]);
            });
    }
}
