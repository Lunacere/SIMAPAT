<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Participant;
use App\Models\ScheduleProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 17)->setTime(12, 0));
    }

    public function test_admin_sees_only_latest_open_incoming_proposals_and_action_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $incoming = $this->clientMeeting('Ajuan klien masuk');
        $this->clientProposal($incoming);
        $outgoing = $this->clientMeeting('Penawaran perusahaan');
        $this->clientProposal($outgoing);
        ScheduleProposal::factory()->create(['meeting_id' => $outgoing->id, 'proposed_party' => 'company']);
        $past = $this->clientMeeting('Negosiasi sudah lewat', ['starts_at' => now()->subHour()]);
        $this->clientProposal($past);
        $closed = $this->clientMeeting('Negosiasi dibatalkan', ['status' => 'cancelled']);
        $this->clientProposal($closed);
        $accepted = $this->clientMeeting('Negosiasi disepakati');
        $this->clientProposal($accepted, ['status' => 'accepted']);
        $stale = $this->clientMeeting('Ajuan versi lama', ['version' => 2]);
        $this->clientProposal($stale, ['meeting_version' => 1]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()->assertViewHas('awaitingCount', 1)
            ->assertViewHas('awaitingMeetings', fn ($meetings): bool => $meetings->modelKeys() === [$incoming->id])
            ->assertSee('Ajuan klien masuk')->assertSee('Tanggapi')
            ->assertSee(route('negotiations.index', ['awaiting' => 'me']), false)
            ->assertSee(route('meetings.index', ['upcoming' => 1]), false)
            ->assertSee('href="#attention-tasks"', false);
    }

    public function test_operator_queue_is_limited_to_owned_meetings_even_when_participating_elsewhere(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $ownMeeting = $this->clientMeeting('Ajuan rapat sendiri', ['creator_id' => $operator->id]);
        $this->clientProposal($ownMeeting);
        $otherMeeting = $this->clientMeeting('Ajuan operator lain');
        $this->clientProposal($otherMeeting);
        Participant::factory()->create(['meeting_id' => $otherMeeting->id, 'user_id' => $operator->id]);
        $secretMeeting = $this->clientMeeting('Ajuan rahasia di luar akses');
        $this->clientProposal($secretMeeting);
        FollowUp::factory()->create(['meeting_id' => $ownMeeting->id, 'task' => 'Pantau tugas rapat sendiri']);
        FollowUp::factory()->create(['user_id' => $operator->id, 'task' => 'Tugas pribadi operator']);
        FollowUp::factory()->create(['meeting_id' => $otherMeeting->id, 'task' => 'Tugas orang lain bukan tanggung jawab']);

        $response = $this->actingAs($operator)->get(route('dashboard'));

        $response->assertOk()->assertViewHas('awaitingCount', 1)
            ->assertViewHas('awaitingMeetings', fn ($meetings): bool => $meetings->modelKeys() === [$ownMeeting->id])
            ->assertViewHas('openTaskCount', 2)
            ->assertSee('Pantau tugas rapat sendiri')->assertSee('Tugas pribadi operator')
            ->assertDontSee('Ajuan rahasia di luar akses')->assertDontSee('Tugas orang lain bukan tanggung jawab');
    }

    public function test_basic_user_sees_only_own_active_tasks_and_participant_meetings_without_company_queue(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $meeting = Meeting::factory()->create(['title' => 'Rapat peserta sendiri']);
        Participant::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
        FollowUp::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'task' => 'Tugas saya yang aktif']);
        FollowUp::factory()->create(['meeting_id' => $meeting->id, 'task' => 'Tugas peserta lain']);
        FollowUp::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id, 'task' => 'Tugas saya sudah selesai', 'status' => 'completed']);
        $privateMeeting = $this->clientMeeting('RAHASIA KLIEN LAIN');
        $this->clientProposal($privateMeeting);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertViewHas('upcomingCount', 1)->assertViewHas('openTaskCount', 1)
            ->assertSee('Tugas aktif saya')->assertSee('Tugas saya yang aktif')->assertSee('Rapat peserta sendiri')
            ->assertSee(route('tasks.index', ['status' => 'active']), false)
            ->assertDontSee('Tugas peserta lain')->assertDontSee('Tugas saya sudah selesai')
            ->assertDontSee('RAHASIA KLIEN LAIN')->assertDontSee('Ajuan perlu tanggapan');
    }

    public function test_attention_tasks_exclude_completed_and_cancelled_and_put_oldest_overdue_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        FollowUp::factory()->count(6)->create(['status' => 'completed', 'deadline' => today()->subMonth()]);
        $overdue = FollowUp::factory()->create(['task' => 'Terlambat sejak lama', 'deadline' => today()->subDays(4)]);
        $recent = FollowUp::factory()->create(['task' => 'Terlambat kemarin', 'deadline' => today()->subDay()]);
        $todayTask = FollowUp::factory()->create(['task' => 'Tenggat hari ini', 'deadline' => today()]);
        FollowUp::factory()->create(['task' => 'Tugas pada rapat batal', 'deadline' => today()->subWeek(), 'meeting_id' => Meeting::factory()->create(['status' => 'cancelled'])->id]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()->assertViewHas('openTaskCount', 3)->assertViewHas('overdueTaskCount', 2)
            ->assertViewHas('tasks', fn ($tasks): bool => $tasks->modelKeys() === [$overdue->id, $recent->id, $todayTask->id])
            ->assertSeeInOrder(['Terlambat sejak lama', 'Terlambat kemarin', 'Tenggat hari ini'])
            ->assertDontSee('Tugas pada rapat batal');
    }

    public function test_incoming_proposal_titles_and_proposer_names_are_escaped(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $meeting = $this->clientMeeting('<script>unsafe-title</script>');
        $client = User::factory()->create(['role' => 'client', 'client_id' => $meeting->client_id, 'name' => '<script>unsafe-name</script>']);
        $this->clientProposal($meeting, ['proposed_by' => $client->id]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()->assertSee('&lt;script&gt;unsafe-title&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;unsafe-name&lt;/script&gt;', false)
            ->assertDontSee('<script>unsafe-title</script>', false)->assertDontSee('<script>unsafe-name</script>', false);
    }

    public function test_upcoming_meeting_displays_both_dates_for_a_cross_day_schedule(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $meeting = Meeting::factory()->create(['starts_at' => '2026-10-20 23:00:00', 'ends_at' => '2026-10-21 01:00:00']);
        Participant::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSeeInOrder(['Mulai: 20 Oktober 2026', '23:00 WIB', 'Selesai: 21 Oktober 2026', '01:00 WIB']);
    }

    public function test_client_is_redirected_away_from_internal_task_dashboard(): void
    {
        $client = User::factory()->create(['role' => 'client', 'client_id' => Client::factory()]);

        $this->actingAs($client)->get(route('dashboard'))->assertRedirect(route('negotiations.index'));
    }

    private function clientMeeting(string $title, array $attributes = []): Meeting
    {
        return Meeting::factory()->create(['client_id' => Client::factory(), 'title' => $title, ...$attributes]);
    }

    private function clientProposal(Meeting $meeting, array $attributes = []): ScheduleProposal
    {
        return ScheduleProposal::factory()->create(['meeting_id' => $meeting->id, 'proposed_party' => 'client',
            'proposed_by' => $attributes['proposed_by'] ?? User::factory()->create(['role' => 'client', 'client_id' => $meeting->client_id])->id, ...$attributes]);
    }
}
