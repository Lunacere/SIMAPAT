<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FormFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_invalid_registration_preserves_non_secret_input_and_identifies_the_submitted_form(): void
    {
        $response = $this->followingRedirects()->from('/register')->post('/register', [
            '_form' => '/register:POST',
            'name' => 'Nama dipertahankan',
            'username' => 'USERNAME SALAH',
            'email' => 'alamat-salah',
            'password' => 'rahasia-pendek',
            'password_confirmation' => 'berbeda',
        ])->assertOk();

        $response->assertSee('data-validation-summary', false)
            ->assertSee('data-submitted-form="/register:POST"', false)
            ->assertSee('data-error-field="username"', false)
            ->assertSee('value="Nama dipertahankan"', false)
            ->assertSee('value="USERNAME SALAH"', false)
            ->assertDontSee('rahasia-pendek');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_validation_summary_escapes_messages_and_handles_array_field_keys(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'participants.0' => ['Peserta <script>alert("pesan")</script> tidak valid.'],
        ]));

        $this->view('layouts.validation-errors', ['errors' => $errors])
            ->assertSee('data-error-field="participants.0"', false)
            ->assertSee('Peserta &lt;script&gt;', false)
            ->assertDontSee('<script>alert("pesan")</script>', false);
    }

    public function test_task_validation_preserves_only_the_submitted_task_form(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $participant = User::factory()->create();
        $meeting = Meeting::factory()->create(['creator_id' => $operator->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->subHours(22)]);
        Participant::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $participant->id]);
        MeetingMinute::factory()->create(['meeting_id' => $meeting->id]);
        $firstTask = FollowUp::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $participant->id, 'task' => 'Tugas pertama']);
        $secondTask = FollowUp::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $participant->id, 'task' => 'Tugas kedua']);
        $action = '/meetings/'.$meeting->id.'/follow-ups/'.$secondTask->id;

        $response = $this->actingAs($operator)->followingRedirects()->from('/meetings/'.$meeting->id)->patch($action, [
            '_form' => $action.':PATCH', 'task' => 'Draf hanya tugas kedua', 'user_id' => $participant->id,
            'deadline' => 'bukan-tanggal', 'status' => 'pending',
        ])->assertOk()->assertSee('data-error-field="deadline"', false);

        $response->assertSee('data-submitted-form="'.$action.':PATCH"', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertSame('Tugas pertama', $document->getElementById('task-'.$firstTask->id)->textContent);
        $this->assertSame('Draf hanya tugas kedua', $document->getElementById('task-'.$secondTask->id)->textContent);
        $this->assertSame('', $document->getElementById('task-new')->textContent);
        $this->assertDatabaseHas('follow_ups', ['id' => $secondTask->id, 'task' => 'Tugas kedua']);
    }

    public function test_malformed_form_identity_does_not_break_the_guest_error_summary(): void
    {
        $this->followingRedirects()->from('/login')->post('/login', ['_form' => ['malformed']])
            ->assertOk()->assertSee('data-error-field="username"', false);
    }
}
