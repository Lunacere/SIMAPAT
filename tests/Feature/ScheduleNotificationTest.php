<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingNegotiation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleNotificationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['queue.default' => 'database', 'mail.default' => 'array']);
    }

    public function test_committed_invitation_stores_a_website_notification_and_queues_a_client_email(): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $meeting = Meeting::factory()->create(['client_id' => $client->id, 'title' => 'Diskusi jadwal klien']);
        DB::transaction(fn () => app(MeetingNegotiation::class)->invite($meeting));
        $this->assertSame(1, $account->notifications()->count());
        $this->assertDatabaseCount('jobs', 1);
        $this->actingAs($account)->get('/reminders')->assertOk()->assertSee('Ajuan jadwal rapat')->assertSee('Diskusi jadwal klien');
        $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame($account->email, $email->getTo()[0]->getAddress());
        $this->assertSame('Ajuan jadwal rapat: Diskusi jadwal klien', $email->getSubject());
        $this->assertStringContainsString(route('negotiations.show', $meeting), $email->getHtmlBody());
        $this->assertStringContainsString('WIB', $email->getTextBody());
    }

    public function test_rolled_back_invitation_does_not_send_notifications_or_queue_an_email(): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        DB::beginTransaction();
        app(MeetingNegotiation::class)->invite($meeting);
        DB::rollBack();
        $this->assertDatabaseCount('schedule_proposals', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(0, $account->notifications()->count());
    }

    public function test_queued_email_is_not_sent_for_an_outdated_meeting_version(): void
    {
        $client = Client::factory()->create();
        User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        app(MeetingNegotiation::class)->invite($meeting);
        $meeting->update(['version' => 2]);
        $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->assertSuccessful();
        $this->assertCount(0, app('mail.manager')->mailer()->getSymfonyTransport()->messages());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_queued_email_is_not_sent_after_client_access_is_revoked(): void
    {
        $client = Client::factory()->create();
        $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        app(MeetingNegotiation::class)->invite($meeting);
        $account->update(['client_id' => Client::factory()->create()->id]);
        $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->assertSuccessful();
        $this->assertCount(0, app('mail.manager')->mailer()->getSymfonyTransport()->messages());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }
}
