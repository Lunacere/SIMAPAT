<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\Participant;
use App\Models\ScheduleProposal;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NegotiationUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 17)->setTime(9, 0));
    }

    public static function recipientStates(): array
    {
        return [
            'client receives company offer' => ['client', 'company', 'Perlu tanggapan Anda'],
            'client awaits company response' => ['client', 'client', 'Menunggu perusahaan'],
            'operator receives client offer' => ['operator', 'client', 'Perlu tanggapan Anda'],
            'operator awaits client response' => ['operator', 'company', 'Menunggu klien'],
            'admin receives client offer' => ['admin', 'client', 'Perlu tanggapan Anda'],
            'admin awaits client response' => ['admin', 'company', 'Menunggu klien'],
        ];
    }

    #[DataProvider('recipientStates')]
    public function test_pending_cards_show_who_needs_to_respond_and_distinguish_proposed_dates(string $role, string $party, string $label): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $meeting = Meeting::factory()->create(['client_id' => $client->id, 'starts_at' => '2026-10-24 09:00:00', 'ends_at' => '2026-10-24 10:00:00']);
        ScheduleProposal::factory()->for($meeting)->create(['proposed_party' => $party,
            'proposed_by' => $party === 'client' ? $account->id : $meeting->creator_id,
            'starts_at' => '2026-10-25 23:00:00', 'ends_at' => '2026-10-26 01:00:00']);
        $actor = match ($role) {
            'client' => $account,
            'operator' => $meeting->creator,
            default => User::factory()->create(['role' => 'admin']),
        };

        $this->actingAs($actor)->get(route('negotiations.index'))->assertOk()->assertSeeText($label)
            ->assertSeeTextInOrder(['Jadwal rapat saat ini', '24 Oktober 2026', '09:00–10:00 WIB', 'Ajuan terbaru', '25 Oktober 2026', '23:00', '26 Oktober 2026', '01:00']);
    }

    public static function negotiatingRoles(): array
    {
        return ['admin' => ['admin'], 'operator' => ['operator'], 'client' => ['client']];
    }

    #[DataProvider('negotiatingRoles')]
    public function test_awaiting_filter_only_lists_current_open_received_offers_in_the_actor_scope(string $role): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $operator = User::factory()->create(['role' => 'operator']);
        $receivedParty = $role === 'client' ? 'company' : 'client';
        $received = Meeting::factory()->create(['creator_id' => $operator->id, 'client_id' => $client->id]);
        ScheduleProposal::factory()->for($received)->create(['proposed_party' => $receivedParty]);
        $sent = Meeting::factory()->create(['creator_id' => $operator->id, 'client_id' => $client->id]);
        ScheduleProposal::factory()->for($sent)->create(['proposed_party' => $receivedParty === 'client' ? 'company' : 'client']);
        $closed = Meeting::factory()->create(['creator_id' => $operator->id, 'client_id' => $client->id, 'status' => 'cancelled']);
        ScheduleProposal::factory()->for($closed)->create(['proposed_party' => $receivedParty]);
        $started = Meeting::factory()->create(['creator_id' => $operator->id, 'client_id' => $client->id, 'starts_at' => now()->subHour()]);
        ScheduleProposal::factory()->for($started)->create(['proposed_party' => $receivedParty]);
        $stale = Meeting::factory()->create(['creator_id' => $operator->id, 'client_id' => $client->id, 'version' => 2]);
        ScheduleProposal::factory()->for($stale)->create(['proposed_party' => $receivedParty, 'meeting_version' => 1]);
        $answered = Meeting::factory()->create(['creator_id' => $operator->id, 'client_id' => $client->id]);
        ScheduleProposal::factory()->for($answered)->create(['proposed_party' => $receivedParty]);
        ScheduleProposal::factory()->for($answered)->create(['proposed_party' => $receivedParty, 'status' => 'accepted']);
        if ($role !== 'admin') {
            $other = Meeting::factory()->create(['client_id' => Client::factory()]);
            ScheduleProposal::factory()->for($other)->create(['proposed_party' => $receivedParty]);
        }
        $actor = match ($role) {
            'client' => $account,
            'operator' => $operator,
            default => User::factory()->create(['role' => 'admin']),
        };

        $this->actingAs($actor)->get(route('negotiations.index', ['awaiting' => 'me']))->assertOk()
            ->assertViewHas('meetings', fn ($meetings): bool => $meetings->getCollection()->modelKeys() === [$received->id])
            ->assertSeeText('Menampilkan ajuan yang perlu Anda tanggapi.')
            ->assertSee(route('negotiations.index'));
    }

    public function test_closed_negotiations_do_not_claim_a_response_is_due_and_history_dates_are_readable(): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $meeting = Meeting::factory()->create(['client_id' => $client->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);
        ScheduleProposal::factory()->for($meeting)->create(['starts_at' => '2026-10-28 23:00:00', 'ends_at' => '2026-10-29 01:00:00']);

        $this->actingAs($account)->get(route('negotiations.index'))->assertOk()->assertSeeText('Negosiasi ditutup')
            ->assertDontSeeText('Perlu tanggapan Anda')->assertDontSeeText('Menunggu klien');
        $this->get(route('negotiations.show', $meeting))->assertOk()->assertSeeText('Ajuan ditutup')
            ->assertSeeText(['28 Oktober 2026', '29 Oktober 2026', '23:00', '01:00'])
            ->assertDontSeeText('28-10-2026 23:00')->assertDontSeeText('Menunggu tanggapan');
    }

    public function test_upcoming_meeting_filter_excludes_closed_and_started_meetings(): void
    {
        $future = Meeting::factory()->create();
        $now = Meeting::factory()->create(['starts_at' => now()]);
        Meeting::factory()->create(['starts_at' => now()->subMinute()]);
        Meeting::factory()->create(['status' => 'completed']);
        Meeting::factory()->create(['status' => 'cancelled']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('meetings.index', ['upcoming' => 1]))->assertOk()
            ->assertViewHas('meetings', fn ($meetings): bool => $meetings->getCollection()->modelKeys() === [$future->id, $now->id]);
    }

    public function test_negotiated_edit_form_uses_stored_times_after_validation_failure_and_keeps_other_input(): void
    {
        $client = Client::factory()->create();
        User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $meeting = Meeting::factory()->create(['client_id' => $client->id, 'starts_at' => '2026-10-24 09:00:00', 'ends_at' => '2026-10-24 10:00:00']);
        $participant = Participant::factory()->create(['meeting_id' => $meeting->id]);
        $this->actingAs($meeting->creator)->from(route('meetings.edit', $meeting))->put(route('meetings.update', $meeting), [
            'title' => 'Judul yang ingin dipertahankan', 'starts_at' => '2026-10-25T13:00', 'ends_at' => '2026-10-25T14:00',
            'client_id' => $client->id, 'location' => '', 'participants' => [$participant->user_id], 'version' => $meeting->version,
            'agenda_title' => 'Agenda', 'agenda_description' => 'Pembahasan',
        ])->assertRedirect(route('meetings.edit', $meeting))->assertSessionHasErrors('location');

        $response = $this->get(route('meetings.edit', $meeting))->assertOk()->assertSeeText('Waktu rapat ini dikelola melalui negosiasi')
            ->assertSee(route('negotiations.show', $meeting));
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $start = $xpath->query('//input[@name="starts_at"]')->item(0);
        $end = $xpath->query('//input[@name="ends_at"]')->item(0);
        $this->assertSame('2026-10-24T09:00', $start->getAttribute('value'));
        $this->assertSame('2026-10-24T10:00', $end->getAttribute('value'));
        $this->assertTrue($start->hasAttribute('readonly'));
        $this->assertTrue($end->hasAttribute('readonly'));
        $this->assertSame('Judul yang ingin dipertahankan', $xpath->query('//input[@name="title"]')->item(0)->getAttribute('value'));
        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'starts_at' => '2026-10-24 09:00:00', 'version' => 1]);
    }

    public function test_edit_form_without_linked_client_accounts_keeps_times_editable(): void
    {
        $meeting = Meeting::factory()->create(['client_id' => Client::factory(), 'starts_at' => '2026-10-24 09:00:00']);
        $response = $this->actingAs($meeting->creator)->get(route('meetings.edit', $meeting))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $start = (new DOMXPath($document))->query('//input[@name="starts_at"]')->item(0);
        $this->assertFalse($start->hasAttribute('readonly'));
        $this->assertSame('2026-10-24T09:00', $start->getAttribute('value'));
    }

    public static function emptyRoles(): array
    {
        return [
            'admin' => ['admin', 'Belum ada negosiasi jadwal', 'setelah Operator menjadwalkan'],
            'operator' => ['operator', 'Belum ada rapat dengan klien', 'Jadwalkan rapat dan pilih klien'],
            'unlinked client' => ['client', 'Belum ada rapat untuk dinegosiasikan', 'Akun Anda belum ditautkan'],
        ];
    }

    #[DataProvider('emptyRoles')]
    public function test_empty_lists_explain_the_next_step_for_each_role(string $role, string $heading, string $guidance): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('negotiations.index'))->assertOk()
            ->assertSeeText([$heading, $guidance]);
    }
}
