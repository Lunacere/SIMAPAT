<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimapatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
    }

    private function meetingData(User $participant, array $extra = []): array
    {
        return array_merge([
            'title' => 'Rapat kebutuhan', 'starts_at' => '2026-10-14T09:00', 'ends_at' => '2026-10-14T10:00',
            'location' => 'Ruang diskusi', 'client_id' => null, 'participants' => [$participant->id],
            'agenda_title' => 'Kebutuhan', 'agenda_description' => 'Tinjau kebutuhan website',
        ], $extra);
    }

    private function meetingFor(User $operator, User $participant, array $extra = []): Meeting
    {
        $meeting = Meeting::factory()->create(array_merge(['creator_id' => $operator->id], $extra));
        Participant::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $participant->id]);

        return $meeting;
    }

    public function test_login_logout_and_private_pages(): void
    {
        $user = User::factory()->create(['username' => 'anggota', 'password' => 'password-aman-123']);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/login', ['username' => 'anggota', 'password' => 'salah'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => 'anggota', 'password' => 'password-aman-123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee('Halo,');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/register')->assertOk()->assertSee('Buat akun SIMAPAT');
    }

    public function test_profile_redirects_guests_to_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_profile_displays_only_the_signed_in_account_and_escapes_its_name(): void
    {
        $user = User::factory()->create(['name' => '<script>alert("profile")</script>', 'username' => 'akun_saya', 'email' => 'saya@example.com']);
        $otherUser = User::factory()->create(['email' => 'akun-lain@example.com']);

        $this->actingAs($user)->get('/profile?user_id='.$otherUser->id)
            ->assertOk()
            ->assertSee('akun_saya')
            ->assertSee('saya@example.com')
            ->assertSee('&lt;script&gt;alert(&quot;profile&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("profile")</script>', false)
            ->assertDontSee('akun-lain@example.com')
            ->assertDontSee($user->password);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['username' => 'unknown', 'password' => 'salah'])->assertSessionHasErrors('username');
        }
        $this->post('/login', ['username' => 'unknown', 'password' => 'salah'])
            ->assertSessionHasErrors(['username' => 'Terlalu banyak percobaan. Coba lagi dalam 60 detik.']);
    }

    public function test_admin_manages_accounts_without_exposing_passwords(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $data = ['name' => 'Anggota Baru', 'username' => 'anggota_baru', 'email' => 'baru@example.com', 'role' => 'user',
            'password' => 'password-aman-123', 'password_confirmation' => 'password-aman-123'];
        $this->actingAs($admin)->post('/users', $data)->assertRedirect('/users');
        $account = User::where('username', 'anggota_baru')->firstOrFail();
        $this->assertTrue(Hash::check($data['password'], $account->password));
        $this->assertArrayNotHasKey('password', $account->toArray());
        $this->post('/users', $data)->assertSessionHasErrors(['username', 'email']);
        $this->get('/users')->assertOk()->assertDontSee($account->password);
        $this->put('/users/'.$admin->id, array_merge($data, ['username' => $admin->username, 'email' => $admin->email]))
            ->assertSessionHasErrors('role');
        $this->delete('/users/'.$admin->id)->assertForbidden();
        $this->actingAs($account)->get('/users')->assertForbidden();
        $this->post('/users', $data)->assertForbidden();
    }

    public function test_deleting_referenced_accounts_and_clients_preserves_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $client = Client::factory()->create();
        $this->meetingFor($operator, $participant, ['client_id' => $client->id]);
        $this->actingAs($admin)->delete('/users/'.$participant->id)->assertSessionHasErrors('user');
        $this->assertModelExists($participant);
        $this->actingAs($operator)->delete('/clients/'.$client->id)->assertSessionHasErrors('client');
        $this->assertModelExists($client);
    }

    public function test_client_management_is_operator_only(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $this->actingAs($operator)->post('/clients', ['name' => 'Klien Uji', 'information' => 'Awal'])->assertRedirect('/clients');
        $client = Client::where('name', 'Klien Uji')->firstOrFail();
        $this->put('/clients/'.$client->id, ['name' => 'Klien Uji', 'progress' => 'Berjalan'])->assertRedirect('/clients');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/clients')->assertOk();
        $this->post('/clients', ['name' => 'Dilarang'])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/clients')->assertForbidden();
        $this->put('/clients/'.$client->id, ['name' => 'Dilarang'])->assertForbidden();
    }

    public function test_operator_creates_meeting_agenda_and_participants_atomically(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $this->actingAs($operator)->post('/meetings', $this->meetingData($participant))->assertRedirect();
        $meeting = Meeting::firstOrFail();
        $this->assertSame($operator->id, $meeting->creator_id);
        $this->assertSame(1, $meeting->participants()->count());
        $this->assertSame('Kebutuhan', $meeting->agenda->title);
        $this->assertDatabaseCount('reminders', 1);
        $this->post('/meetings', $this->meetingData($participant, ['ends_at' => '2026-10-14T08:00']))->assertSessionHasErrors('ends_at');
        $this->post('/meetings', $this->meetingData($participant, ['participants' => [$participant->id, $participant->id]]))->assertSessionHasErrors();
        $this->assertDatabaseCount('meetings', 1);
        $this->assertDatabaseCount('agendas', 1);
    }

    public static function overlapCases(): array
    {
        return [
            'partial' => ['2026-10-14T09:30', '2026-10-14T10:30'],
            'inside' => ['2026-10-14T09:10', '2026-10-14T09:50'],
            'contains' => ['2026-10-14T08:00', '2026-10-14T11:00'],
            'same' => ['2026-10-14T09:00', '2026-10-14T10:00'],
        ];
    }

    #[DataProvider('overlapCases')]
    public function test_overlapping_meetings_are_rejected(string $start, string $end): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $this->actingAs($operator)->post('/meetings', $this->meetingData($participant))->assertRedirect();
        $this->post('/meetings', $this->meetingData($participant, ['starts_at' => $start, 'ends_at' => $end]))->assertSessionHasErrors('participants');
        $this->assertDatabaseCount('meetings', 1);
        $this->assertDatabaseCount('participants', 1);
    }

    public function test_adjacent_meetings_and_different_participants_are_allowed(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $this->actingAs($operator)->post('/meetings', $this->meetingData($participant))->assertRedirect();
        $this->post('/meetings', $this->meetingData($participant, ['starts_at' => '2026-10-14T10:00', 'ends_at' => '2026-10-14T11:00']))->assertRedirect();
        $this->post('/meetings', $this->meetingData(User::factory()->create()))->assertRedirect();
        $this->assertDatabaseCount('meetings', 3);
    }

    public function test_edit_checks_participant_changes_and_stale_versions(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $one = User::factory()->create();
        $two = User::factory()->create();
        $this->actingAs($operator)->post('/meetings', $this->meetingData($one))->assertRedirect();
        $meeting = Meeting::firstOrFail();
        $data = $this->meetingData($one, ['version' => 1]);
        $this->put('/meetings/'.$meeting->id, $data)->assertRedirect();
        $this->put('/meetings/'.$meeting->id, $data)->assertSessionHasErrors('title');
        $this->post('/meetings', $this->meetingData($two))->assertRedirect();
        $this->put('/meetings/'.$meeting->id, $this->meetingData($two, ['version' => 2]))->assertSessionHasErrors('participants');
        $this->assertSame($one->id, $meeting->participants()->first()->user_id);
    }

    public function test_private_meetings_are_not_visible_or_editable_across_users(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $meeting = $this->meetingFor($operator, $participant, ['title' => 'RAHASIA RAPAT']);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get('/meetings/'.$meeting->id)->assertForbidden();
        $this->get('/meetings')->assertOk()->assertDontSee('RAHASIA RAPAT');
        $this->get('/dashboard')->assertOk()->assertDontSee('RAHASIA RAPAT');
        $this->actingAs(User::factory()->create(['role' => 'operator']))->get('/meetings/'.$meeting->id.'/edit')->assertForbidden();
        $this->actingAs($participant)->get('/meetings/'.$meeting->id)->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/meetings/'.$meeting->id)->assertOk();
        $this->post('/meetings', $this->meetingData($participant))->assertForbidden();
    }

    public function test_confirmation_and_preferences_belong_to_the_current_participant(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $user = User::factory()->create();
        $meeting = $this->meetingFor($operator, $user);
        $own = $meeting->participants()->first();
        $other = Participant::factory()->create(['meeting_id' => $meeting->id]);
        $this->actingAs($user)->patch('/meetings/'.$meeting->id.'/participants/'.$own->id.'/confirmation', ['attendance_status' => 'accepted'])->assertRedirect();
        $this->assertSame('accepted', $own->fresh()->attendance_status);
        $this->patch('/meetings/'.$meeting->id.'/participants/'.$other->id.'/confirmation', ['attendance_status' => 'accepted'])->assertForbidden();
        $this->put('/meetings/'.$meeting->id.'/participants/'.$own->id.'/reminder', ['reminder_preference' => 'morning'])->assertRedirect();
        $this->put('/meetings/'.$meeting->id.'/participants/'.$other->id.'/reminder', ['reminder_preference' => 'none'])->assertForbidden();
        $foreign = Meeting::factory()->create();
        $this->patch('/meetings/'.$foreign->id.'/participants/'.$own->id.'/confirmation', ['attendance_status' => 'accepted'])->assertForbidden();
    }

    public function test_minutes_are_unique_and_render_script_as_text(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $meeting = $this->meetingFor($operator, $participant, ['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        $data = ['discussion' => '<script>alert(1)</script>', 'decisions' => 'Bangun fitur rapat', 'recorded_on' => today()->toDateString(), 'client_progress' => 'Snapshot awal'];
        $this->actingAs($operator)->put('/meetings/'.$meeting->id.'/minutes', $data)->assertRedirect();
        $this->put('/meetings/'.$meeting->id.'/minutes', $data)->assertRedirect();
        $this->assertDatabaseCount('meeting_minutes', 1);
        $this->actingAs($participant)->get('/meetings/'.$meeting->id)->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->put('/meetings/'.$meeting->id.'/minutes', $data)->assertForbidden();
    }

    public function test_tasks_require_minutes_and_pic_can_only_update_own_status(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $pic = User::factory()->create();
        $meeting = $this->meetingFor($operator, $pic, ['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);
        $data = ['task' => 'Susun kebutuhan', 'user_id' => $pic->id, 'deadline' => today()->addDays(2)->toDateString(), 'status' => 'pending'];
        $this->actingAs($operator)->post('/meetings/'.$meeting->id.'/follow-ups', $data)->assertSessionHasErrors('task');
        MeetingMinute::factory()->create(['meeting_id' => $meeting->id]);
        $this->post('/meetings/'.$meeting->id.'/follow-ups', $data)->assertRedirect();
        $task = FollowUp::firstOrFail();
        $this->actingAs($pic)->patch('/meetings/'.$meeting->id.'/follow-ups/'.$task->id.'/status', ['status' => 'completed', 'user_id' => $operator->id])->assertSessionHasErrors('user_id');
        $this->assertSame($pic->id, $task->fresh()->user_id);
        $this->patch('/meetings/'.$meeting->id.'/follow-ups/'.$task->id.'/status', ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $task->fresh()->status);
        $other = User::factory()->create();
        $this->actingAs($other)->patch('/meetings/'.$meeting->id.'/follow-ups/'.$task->id.'/status', ['status' => 'pending'])->assertForbidden();
        $foreign = Meeting::factory()->create(['creator_id' => $operator->id]);
        $this->actingAs($operator)->delete('/meetings/'.$foreign->id.'/follow-ups/'.$task->id)->assertNotFound();
        $this->assertModelExists($task);
    }

    public function test_assigned_task_does_not_grant_access_to_private_meeting(): void
    {
        $pic = User::factory()->create();
        $meeting = Meeting::factory()->create(['title' => 'RAHASIA SUMBER']);
        FollowUp::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $pic->id, 'task' => 'Tugas yang boleh dibaca']);
        $this->actingAs($pic)->get('/my-tasks')->assertOk()->assertSee('Tugas yang boleh dibaca')->assertDontSee('RAHASIA SUMBER');
        $this->get('/meetings/'.$meeting->id)->assertForbidden();
    }

    public function test_cancel_keeps_history_and_completed_meeting_does_not_require_tasks(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $this->actingAs($operator)->post('/meetings', $this->meetingData($participant))->assertRedirect();
        $meeting = Meeting::firstOrFail();
        $this->patch('/meetings/'.$meeting->id.'/cancel')->assertRedirect();
        $this->assertSame('cancelled', $meeting->fresh()->status);
        $this->assertModelExists($meeting);
        $this->assertSame(0, $meeting->reminders()->where('status', 'pending')->count());
        $past = $this->meetingFor($operator, $participant, ['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        $this->patch('/meetings/'.$past->id.'/complete')->assertSessionHasErrors('status');
        MeetingMinute::factory()->create(['meeting_id' => $past->id]);
        $this->patch('/meetings/'.$past->id.'/complete')->assertRedirect();
        $this->assertSame('completed', $past->fresh()->status);
        $this->assertSame(0, $past->followUps()->count());
    }

    public function test_history_filters_reports_and_form_pages(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $client = Client::factory()->create();
        $meeting = $this->meetingFor($operator, $participant, ['client_id' => $client->id, 'title' => 'Arsip Oktober', 'starts_at' => '2026-10-10 09:00', 'ends_at' => '2026-10-10 10:00']);
        $this->actingAs($operator)->get('/history?from=2026-10-10&to=2026-10-10&participant='.$participant->id.'&client='.$client->id)->assertOk()->assertSee('Arsip Oktober');
        $this->get('/history?to=2026-10-10')->assertOk()->assertSee('Arsip Oktober');
        $this->get('/history?from=2026-10-11&to=2026-10-11')->assertOk()->assertDontSee('Arsip Oktober');
        $this->get('/history?from=2026-10-12&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/meetings/create')->assertOk();
        $this->get('/meetings/'.$meeting->id.'/edit')->assertOk();
        $this->get('/clients/create')->assertOk();
        $this->get('/clients/'.$client->id.'/edit')->assertOk();
        $this->get('/reports/meetings')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/reports/meetings?from=2026-10-10&to=2026-10-10')->assertOk()->assertSee('Arsip Oktober');
        $this->get('/users/create')->assertOk();
        $this->get('/users/'.$operator->id.'/edit')->assertOk();
    }

    public function test_agenda_delete_is_owner_only_and_preserves_meeting(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $this->actingAs($operator)->post('/meetings', $this->meetingData($participant))->assertRedirect();
        $meeting = Meeting::firstOrFail();
        $this->actingAs($participant)->delete('/meetings/'.$meeting->id.'/agenda')->assertForbidden();
        $this->actingAs($operator)->delete('/meetings/'.$meeting->id.'/agenda')->assertRedirect();
        $this->assertDatabaseCount('agendas', 0);
        $this->assertModelExists($meeting);
        $this->assertSame(1, $meeting->participants()->count());
        $this->put('/meetings/'.$meeting->id, $this->meetingData($participant, ['version' => 1]))->assertRedirect();
        $this->assertDatabaseCount('agendas', 1);
        $this->travel(3)->days();
        $this->delete('/meetings/'.$meeting->id.'/agenda')->assertSessionHasErrors('agenda');
        $this->assertDatabaseCount('agendas', 1);
    }

    public function test_admin_editing_own_account_logs_out_current_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put('/users/'.$admin->id, [
            'name' => 'Admin Baru', 'username' => $admin->username,
            'email' => $admin->email, 'role' => 'admin',
        ])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame('Admin Baru', $admin->fresh()->name);
    }
}
