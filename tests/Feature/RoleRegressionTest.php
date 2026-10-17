<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\ScheduleProposalNotification;
use App\Services\MeetingNegotiation;
use App\Services\MeetingScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function accountData(User $user, array $extra = []): array
    {
        return array_merge(['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role' => $user->role], $extra);
    }

    private function meetingData(User $participant, Client $client): array
    {
        return ['title' => 'Rapat regresi', 'starts_at' => now()->addDays(3)->setTime(9, 0)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(3)->setTime(10, 0)->format('Y-m-d H:i:s'), 'location' => 'Ruang A', 'client_id' => $client->id,
            'participants' => [$participant->id], 'agenda_title' => 'Agenda', 'agenda_description' => 'Pembahasan'];
    }

    public function test_editing_agenda_preserves_the_client_counterproposal_and_it_can_still_be_accepted(): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $operator = User::factory()->create(['role' => 'operator']);
        $data = $this->meetingData(User::factory()->create(), $client);
        $meeting = app(MeetingScheduler::class)->save($operator, $data);
        $counter = app(MeetingNegotiation::class)->propose($account, $meeting, ['version' => $meeting->version,
            'latest_proposal_id' => $meeting->latestProposal()->first()->id,
            'starts_at' => now()->addDays(4)->setTime(13, 0)->format('Y-m-d H:i:s'), 'ends_at' => now()->addDays(4)->setTime(14, 0)->format('Y-m-d H:i:s')]);
        app(MeetingScheduler::class)->save($operator, [...$data, 'version' => $meeting->version, 'agenda_description' => 'Perbaikan teks'], $meeting);
        $this->assertSame($counter->id, $meeting->latestProposal()->first()->id);
        $this->assertSame('pending', $counter->refresh()->status);
        $this->assertSame($meeting->refresh()->version, $counter->meeting_version);
        $this->actingAs($operator)->patch(route('negotiations.accept', [$meeting, $counter]))->assertRedirect();
        $this->assertSame('accepted', $counter->refresh()->status);
        $this->assertTrue($meeting->refresh()->starts_at->equalTo($counter->starts_at));
    }

    public static function companyRoles(): array
    {
        return ['admin' => ['admin'], 'operator' => ['operator']];
    }

    #[DataProvider('companyRoles')]
    public function test_company_representatives_can_accept_a_client_proposal(string $role): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $proposal = app(MeetingNegotiation::class)->propose($account, $meeting, ['version' => 1, 'latest_proposal_id' => 0,
            'starts_at' => now()->addDays(5)->setTime(9, 0)->format('Y-m-d H:i:s'), 'ends_at' => now()->addDays(5)->setTime(10, 0)->format('Y-m-d H:i:s')]);
        $actor = $role === 'admin' ? User::factory()->create(['role' => 'admin']) : $meeting->creator;
        $this->actingAs($actor)->patch(route('negotiations.accept', [$meeting, $proposal]))->assertRedirect();
        $this->assertSame('accepted', $proposal->refresh()->status);
        $this->assertSame($actor->id, $proposal->responded_by);
        $this->assertTrue($meeting->refresh()->starts_at->equalTo($proposal->starts_at));
    }

    public function test_admin_offer_requires_client_acceptance_even_when_another_company_account_responds(): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $proposal = app(MeetingNegotiation::class)->propose($admin, $meeting, ['version' => 1, 'latest_proposal_id' => 0,
            'starts_at' => now()->addDays(5)->setTime(9, 0)->format('Y-m-d H:i:s'), 'ends_at' => now()->addDays(5)->setTime(10, 0)->format('Y-m-d H:i:s')]);
        $this->assertSame('company', $proposal->proposed_party);
        $this->actingAs($meeting->creator)->patch(route('negotiations.accept', [$meeting, $proposal]))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('negotiations.accept', [$meeting, $proposal]))->assertForbidden();
        $this->actingAs($account)->get(route('negotiations.show', $meeting))->assertOk()->assertSee('Setujui jadwal ini');
        $this->patch(route('negotiations.accept', [$meeting, $proposal]))->assertRedirect();
        Notification::assertSentTo($account, ScheduleProposalNotification::class);
    }

    public function test_clients_are_rejected_as_pic_on_create_and_update(): void
    {
        $client = User::factory()->create(['role' => 'client', 'client_id' => Client::factory()]);
        $meeting = Meeting::factory()->create();
        MeetingMinute::factory()->create(['meeting_id' => $meeting->id]);
        Participant::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $client->id]);
        $task = FollowUp::factory()->create(['meeting_id' => $meeting->id]);
        $data = ['task' => 'Pekerjaan internal', 'user_id' => $client->id, 'deadline' => now()->addDays(5)->toDateString(), 'status' => 'pending'];
        $this->actingAs($meeting->creator)->post(route('follow-ups.store', $meeting), $data)->assertSessionHasErrors('user_id');
        $this->patch(route('follow-ups.update', [$meeting, $task]), $data)->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('follow_ups', 1);
        $this->assertNotSame($client->id, $task->refresh()->user_id);
    }

    public function test_operator_role_change_is_blocked_until_their_meetings_are_closed(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $meeting = Meeting::factory()->create(['creator_id' => $operator->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('users.update', $operator), $this->accountData($operator, ['role' => 'user']))->assertSessionHasErrors('role');
        $this->assertSame('operator', $operator->refresh()->role);
        $meeting->update(['status' => 'cancelled']);
        $this->put(route('users.update', $operator), $this->accountData($operator, ['role' => 'user']))->assertRedirect();
        $this->assertSame('user', $operator->refresh()->role);
    }

    public function test_linking_a_client_account_notifies_existing_pending_proposals_once(): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['username' => 'calon_klien']);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        app(MeetingNegotiation::class)->propose($meeting->creator, $meeting, ['version' => 1, 'latest_proposal_id' => 0,
            'starts_at' => now()->addDays(5)->setTime(9, 0)->format('Y-m-d H:i:s'), 'ends_at' => now()->addDays(5)->setTime(10, 0)->format('Y-m-d H:i:s')]);
        $admin = User::factory()->create(['role' => 'admin']);
        $data = $this->accountData($account, ['role' => 'client', 'client_id' => (string) $client->id]);
        $this->actingAs($admin)->put(route('users.update', $account), $data)->assertRedirect();
        $this->put(route('users.update', $account), $data)->assertRedirect();
        Notification::assertSentToTimes($account, ScheduleProposalNotification::class, 1);
        $this->assertDatabaseCount('schedule_proposals', 1);
    }

    public function test_internal_pic_cannot_be_converted_into_an_external_client(): void
    {
        $pic = User::factory()->create();
        FollowUp::factory()->create(['user_id' => $pic->id]);
        $client = Client::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('users.update', $pic), $this->accountData($pic, ['role' => 'client', 'client_id' => $client->id]))->assertSessionHasErrors('role');
        $this->assertSame('user', $pic->refresh()->role);
        $this->assertNull($pic->client_id);
    }

    public function test_changing_a_client_link_invalidates_their_pending_offer(): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $proposal = app(MeetingNegotiation::class)->propose($account, $meeting, ['version' => 1, 'latest_proposal_id' => 0,
            'starts_at' => now()->addDays(5)->setTime(9, 0)->format('Y-m-d H:i:s'), 'ends_at' => now()->addDays(5)->setTime(10, 0)->format('Y-m-d H:i:s')]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('users.update', $account),
            $this->accountData($account, ['client_id' => Client::factory()->create()->id]))->assertRedirect();
        $this->assertSame('superseded', $proposal->refresh()->status);
        $this->actingAs($meeting->creator)->patch(route('negotiations.accept', [$meeting, $proposal]))->assertSessionHasErrors('starts_at');
        $this->assertSame(1, $meeting->refresh()->version);
    }
}
