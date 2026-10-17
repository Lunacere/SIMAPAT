<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Participant;
use App\Models\ScheduleProposal;
use App\Models\User;
use App\Notifications\ScheduleProposalNotification;
use App\Services\MeetingNegotiation;
use App\Services\ReminderDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NegotiationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(9, 0));
    }

    private function clientAccount(Client $client): User
    {
        return User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
    }

    private function proposalData(Meeting $meeting, array $extra = []): array
    {
        return array_merge(['starts_at' => '2026-10-17T13:00', 'ends_at' => '2026-10-17T14:00',
            'message' => 'Tim kami tersedia sore hari.', 'version' => $meeting->version,
            'latest_proposal_id' => $meeting->scheduleProposals()->max('id') ?? 0], $extra);
    }

    private function accountData(User $user, array $extra = []): array
    {
        return array_merge(['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role' => 'client'], $extra);
    }

    public function test_admin_can_assign_the_client_role_only_with_an_existing_client(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $account = User::factory()->create(['username' => 'calon_klien']);
        $client = Client::factory()->create();
        $this->actingAs($admin)->put('/users/'.$account->id, $this->accountData($account))->assertSessionHasErrors('client_id');
        $this->put('/users/'.$account->id, $this->accountData($account, ['client_id' => $client->id]))->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['id' => $account->id, 'role' => 'client', 'client_id' => $client->id]);
        $this->actingAs($account->refresh())->get('/users/create')->assertForbidden();
        $this->put('/users/'.$account->id, $this->accountData($account, ['role' => 'admin']))->assertForbidden();
        $this->actingAs($admin)->put('/users/'.$account->id, $this->accountData($account, ['role' => 'user']))->assertRedirect('/users');
        $this->assertNull($account->refresh()->client_id);
    }

    public function test_client_access_is_scoped_to_their_company_and_does_not_expose_internal_meeting_content(): void
    {
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id, 'title' => 'Rapat milik klien']);
        $other = Meeting::factory()->create(['client_id' => Client::factory(), 'title' => 'Rapat rahasia klien lain']);
        $participation = Participant::factory()->create(['meeting_id' => $other->id, 'user_id' => $account->id]);
        $task = FollowUp::factory()->create(['meeting_id' => $other->id, 'user_id' => $account->id]);
        $this->get('/negotiations')->assertRedirect('/login');
        $this->actingAs($account)->get('/negotiations')->assertOk()->assertSee('Rapat milik klien')->assertDontSee('Rapat rahasia klien lain');
        $this->get('/meetings')->assertOk()->assertSee('Rapat milik klien')->assertDontSee('Rapat rahasia klien lain');
        $this->get('/meetings/'.$meeting->id)->assertRedirect(route('negotiations.show', $meeting));
        $this->get(route('negotiations.show', $other))->assertForbidden();
        $this->get('/meetings/'.$other->id)->assertForbidden();
        $this->patch(route('follow-ups.status', [$other, $task]), ['status' => 'completed'])->assertForbidden();
        $this->patch(route('participants.confirmation', [$other, $participation]), ['attendance_status' => 'accepted'])->assertForbidden();
        $this->put(route('participants.reminder', [$other, $participation]), ['reminder_preference' => 'none'])->assertForbidden();
        $this->post(route('negotiations.store', $other), $this->proposalData($other))->assertForbidden();
        $this->get('/dashboard')->assertRedirect('/negotiations');
        $this->get('/my-tasks')->assertForbidden();
        $this->get('/clients')->assertForbidden();
        $this->get('/users')->assertForbidden();
    }

    public function test_scheduling_a_client_meeting_creates_an_invitation_and_notifies_only_linked_clients(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $unrelated = $this->clientAccount(Client::factory()->create());
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $this->actingAs($operator)->post('/meetings', ['title' => 'Diskusi kebutuhan', 'starts_at' => '2026-10-16T09:00', 'ends_at' => '2026-10-16T10:00',
            'location' => 'Ruang A', 'client_id' => $client->id, 'participants' => [$participant->id],
            'agenda_title' => 'Kebutuhan', 'agenda_description' => 'Tinjau kebutuhan klien'])->assertRedirect();
        $meeting = Meeting::firstOrFail();
        $this->assertDatabaseHas('schedule_proposals', ['meeting_id' => $meeting->id, 'proposed_by' => $operator->id, 'status' => 'pending']);
        Notification::assertSentTo($account, ScheduleProposalNotification::class, fn ($notification, $channels): bool => $channels === ['database', 'mail'] && $notification->proposal->meeting_id === $meeting->id);
        Notification::assertNotSentTo($unrelated, ScheduleProposalNotification::class);
        $this->actingAs($account)->get(route('negotiations.show', $meeting))->assertOk()->assertSee('Setujui jadwal ini');
        $this->patch(route('negotiations.accept', [$meeting, $meeting->latestProposal]))->assertRedirect();
        $this->assertSame('accepted', $meeting->latestProposal()->first()->status);
    }

    public function test_counterproposal_changes_the_schedule_only_after_company_acceptance_and_reschedules_reminders(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $participant = Participant::factory()->create(['meeting_id' => $meeting->id, 'attendance_status' => 'accepted']);
        app(ReminderDelivery::class)->schedule($meeting);
        app(MeetingNegotiation::class)->invite($meeting);
        $invitation = $meeting->latestProposal()->firstOrFail();
        $originalStartsAt = $meeting->starts_at->format('Y-m-d H:i:s');
        $this->actingAs($account)->post(route('negotiations.store', $meeting), $this->proposalData($meeting))->assertRedirect();
        $counter = $meeting->latestProposal()->firstOrFail();
        $this->assertSame('superseded', $invitation->refresh()->status);
        $this->assertSame($originalStartsAt, $meeting->refresh()->starts_at->format('Y-m-d H:i:s'));
        Notification::assertSentTo($meeting->creator, ScheduleProposalNotification::class);
        $this->patch(route('negotiations.accept', [$meeting, $counter]))->assertForbidden();
        $this->actingAs($meeting->creator)->patch(route('negotiations.accept', [$meeting, $counter]))->assertRedirect();
        $this->assertSame('accepted', $counter->refresh()->status);
        $this->assertSame('2026-10-17 13:00:00', $meeting->refresh()->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(2, $meeting->version);
        $this->assertSame('pending', $participant->refresh()->attendance_status);
        $this->assertDatabaseHas('reminders', ['meeting_id' => $meeting->id, 'meeting_version' => 1, 'status' => 'cancelled']);
        $this->assertDatabaseHas('reminders', ['meeting_id' => $meeting->id, 'meeting_version' => 2, 'status' => 'pending']);
        Notification::assertSentTo($account, ScheduleProposalNotification::class, fn ($notification): bool => $notification->proposal->status === 'accepted');
        $this->patch(route('negotiations.accept', [$meeting, $counter]))->assertSessionHasErrors('starts_at');
    }

    public function test_stale_forms_and_superseded_proposals_cannot_overwrite_newer_negotiations(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        app(MeetingNegotiation::class)->invite($meeting);
        $old = $meeting->latestProposal()->firstOrFail();
        $data = $this->proposalData($meeting);
        $this->actingAs($account)->post(route('negotiations.store', $meeting), $data)->assertRedirect();
        $this->post(route('negotiations.store', $meeting), $data)->assertSessionHasErrors('starts_at');
        $this->patch(route('negotiations.accept', [$meeting, $old]))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('schedule_proposals', 2);
        $this->assertSame(1, $meeting->refresh()->version);
    }

    public function test_acceptance_rejects_conflicting_client_schedules_without_changing_either_meeting(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        Meeting::factory()->create(['client_id' => $client->id, 'starts_at' => '2026-10-17 13:00:00', 'ends_at' => '2026-10-17 14:00:00']);
        $this->actingAs($account)->post(route('negotiations.store', $meeting), $this->proposalData($meeting))->assertRedirect();
        $proposal = $meeting->latestProposal()->firstOrFail();
        $this->actingAs($meeting->creator)->patch(route('negotiations.accept', [$meeting, $proposal]))->assertSessionHasErrors('participants');
        $this->assertSame('pending', $proposal->refresh()->status);
        $this->assertSame(1, $meeting->refresh()->version);
    }

    public function test_negotiation_rejects_invalid_dates_and_closed_meetings(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $this->actingAs($account)->post(route('negotiations.store', $meeting), $this->proposalData($meeting, ['ends_at' => '2026-10-17T12:00']))->assertSessionHasErrors('ends_at');
        $this->post(route('negotiations.store', $meeting), $this->proposalData($meeting, ['starts_at' => '2026-10-12T12:00']))->assertSessionHasErrors('starts_at');
        $meeting->update(['status' => 'cancelled']);
        $this->post(route('negotiations.store', $meeting), $this->proposalData($meeting))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('schedule_proposals', 0);
        Notification::assertNothingSent();
    }

    public function test_negotiation_history_escapes_client_messages(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $message = '<script>alert("negotiation")</script>';
        $this->actingAs($account)->post(route('negotiations.store', $meeting), $this->proposalData($meeting, ['message' => $message]))->assertRedirect();
        $this->get(route('negotiations.show', $meeting))->assertOk()->assertSee($message)->assertDontSee($message, false);
    }

    public function test_acceptance_rejects_a_proposal_from_a_different_meeting(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $otherMeeting = Meeting::factory()->create(['client_id' => $client->id]);
        $proposal = ScheduleProposal::factory()->for($otherMeeting)->create(['proposed_by' => $otherMeeting->creator_id]);
        $this->actingAs($account)->patch(route('negotiations.accept', [$meeting, $proposal]))->assertNotFound();
        $this->assertSame('pending', $proposal->refresh()->status);
        $this->assertSame(1, $meeting->refresh()->version);
    }

    public function test_operator_cannot_bypass_client_agreement_by_editing_the_meeting_time(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $participant = Participant::factory()->create(['meeting_id' => $meeting->id]);
        $this->actingAs($meeting->creator)->put('/meetings/'.$meeting->id, ['title' => $meeting->title,
            'starts_at' => '2026-10-17T13:00', 'ends_at' => '2026-10-17T14:00', 'client_id' => $client->id,
            'location' => $meeting->location, 'participants' => [$participant->user_id], 'version' => $meeting->version,
            'agenda_title' => 'Agenda', 'agenda_description' => 'Pembahasan'])->assertSessionHasErrors('starts_at');
        $this->assertSame(1, $meeting->refresh()->version);
        Notification::assertNothingSent();
    }

    public function test_only_company_representatives_and_linked_clients_can_negotiate(): void
    {
        Notification::fake();
        $meeting = Meeting::factory()->create(['client_id' => Client::factory()]);
        $basic = User::factory()->create();
        Participant::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $basic->id]);
        $this->actingAs($basic)->get(route('negotiations.show', $meeting))->assertForbidden();
        $this->post(route('negotiations.store', $meeting), $this->proposalData($meeting))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'operator']))->post(route('negotiations.store', $meeting), $this->proposalData($meeting))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('negotiations.show', $meeting))->assertOk()->assertSee('Ajukan jadwal lain');
        $this->post(route('negotiations.store', $meeting), $this->proposalData($meeting))->assertRedirect();
        $this->assertDatabaseCount('schedule_proposals', 1);
    }

    public static function readableProposalDates(): array
    {
        return [
            'same day' => ['2026-10-28 09:00:00', '2026-10-28 10:00:00', ['Rabu, 28 Oktober 2026', '09:00', '10:00', 'WIB']],
            'overnight' => ['2026-10-28 23:00:00', '2026-10-29 01:00:00', ['Mulai', 'Rabu, 28 Oktober 2026', '23:00 WIB', 'Selesai', 'Kamis, 29 Oktober 2026', '01:00 WIB']],
            'next year' => ['2026-12-31 23:00:00', '2027-01-01 01:00:00', ['Kamis, 31 Desember 2026', '23:00 WIB', 'Jumat, 1 Januari 2027', '01:00 WIB']],
        ];
    }

    #[DataProvider('readableProposalDates')]
    public function test_proposal_dates_show_the_full_date_and_both_times(string $startsAt, string $endsAt, array $readableDates): void
    {
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        ScheduleProposal::factory()->for($meeting)->create(['proposed_by' => $account->id, 'proposed_party' => 'client',
            'starts_at' => $startsAt, 'ends_at' => $endsAt, 'message' => null]);

        $this->actingAs($meeting->creator)->get(route('negotiations.show', $meeting))
            ->assertOk()->assertSeeText($readableDates)->assertDontSeeText('Catatan pengusul');
    }
}
