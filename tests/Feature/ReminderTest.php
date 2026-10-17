<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
    }

    private function scheduled(string $preference = 'day_before', array $extra = []): Meeting
    {
        $meeting = Meeting::factory()->create(array_merge(['starts_at' => '2026-10-14 10:00', 'ends_at' => '2026-10-14 11:00'], $extra));
        Participant::factory()->create(['meeting_id' => $meeting->id, 'reminder_preference' => $preference]);
        app(ReminderDelivery::class)->schedule($meeting);

        return $meeting;
    }

    public function test_day_before_and_morning_use_jakarta_time_and_do_not_duplicate(): void
    {
        $meeting = $this->scheduled();
        $this->assertSame('2026-10-13 10:00:00', $meeting->reminders()->first()->scheduled_at->format('Y-m-d H:i:s'));
        app(ReminderDelivery::class)->schedule($meeting);
        $this->assertSame(1, $meeting->reminders()->count());
        $morning = $this->scheduled('morning');
        $this->assertSame('2026-10-14 07:00:00', $morning->reminders()->first()->scheduled_at->format('Y-m-d H:i:s'));
        $early = $this->scheduled('morning', ['starts_at' => '2026-10-14 06:00', 'ends_at' => '2026-10-14 06:30']);
        $this->assertSame('2026-10-13 06:00:00', $early->reminders()->first()->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, $this->scheduled('none')->reminders()->count());
    }

    public function test_due_reminder_is_sent_once_and_cli_is_idempotent(): void
    {
        $meeting = $this->scheduled();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 0));
        $this->artisan('meetings:send-reminders')->assertSuccessful();
        $user = $meeting->participants()->first()->user;
        $this->assertSame(1, $user->notifications()->count());
        $reminder = $meeting->reminders()->first();
        $this->assertSame('sent', $reminder->status);
        $this->assertNotNull($reminder->sent_at);
        $this->artisan('meetings:send-reminders')->assertSuccessful();
        app(ReminderDelivery::class)->schedule($meeting);
        $this->assertSame(0, app(ReminderDelivery::class)->deliverDue());
        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(1, $meeting->reminders()->count());
    }

    public function test_rescheduling_cancels_old_version_and_removed_participant_is_skipped(): void
    {
        $meeting = $this->scheduled();
        $old = $meeting->reminders()->first();
        $meeting->update(['starts_at' => '2026-10-15 10:00', 'ends_at' => '2026-10-15 11:00', 'version' => 2]);
        app(ReminderDelivery::class)->schedule($meeting);
        $this->assertSame('cancelled', $old->fresh()->status);
        $new = $meeting->reminders()->where('status', 'pending')->firstOrFail();
        $this->assertSame(2, $new->meeting_version);
        $user = $meeting->participants()->first()->user;
        $meeting->participants()->delete();
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(10, 0));
        $this->assertSame(0, app(ReminderDelivery::class)->deliverDue());
        $this->assertSame('skipped', $new->fresh()->status);
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_cancel_decline_and_started_meetings_do_not_send(): void
    {
        $cancelled = $this->scheduled();
        $cancelled->update(['status' => 'cancelled']);
        $declined = $this->scheduled();
        $declined->participants()->update(['attendance_status' => 'declined']);
        $expired = $this->scheduled();
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(10, 0));
        $this->assertSame(0, app(ReminderDelivery::class)->deliverDue());
        $this->assertSame(3, Reminder::where('status', 'skipped')->count());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_late_preference_is_due_now_but_cannot_resend_after_delivery(): void
    {
        $meeting = $this->scheduled('morning', ['starts_at' => '2026-10-12 12:00', 'ends_at' => '2026-10-12 13:00']);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $meeting->reminders()->first()->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, app(ReminderDelivery::class)->deliverDue());
        $this->travel(10)->minutes();
        $meeting->participants()->update(['reminder_preference' => 'day_before']);
        app(ReminderDelivery::class)->schedule($meeting);
        $this->assertSame(0, app(ReminderDelivery::class)->deliverDue());
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_delivery_failure_rolls_back_and_retries_on_next_run(): void
    {
        $meeting = $this->scheduled();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 0));
        $dispatcher = Notification::getFacadeRoot();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Simulated notification failure'));
        $this->assertSame(0, app(ReminderDelivery::class)->deliverDue());
        $this->assertSame('pending', $meeting->reminders()->first()->status);
        $this->assertDatabaseCount('notifications', 0);
        Notification::swap($dispatcher);
        $this->assertSame(1, app(ReminderDelivery::class)->deliverDue());
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notification_reads_are_private_and_revoked_access_hides_details(): void
    {
        $meeting = $this->scheduled('day_before', ['title' => 'Judul rahasia']);
        $user = $meeting->participants()->first()->user;
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 0));
        app(ReminderDelivery::class)->deliverDue();
        $notification = $user->notifications()->firstOrFail();
        $this->actingAs($user)->get('/reminders')->assertOk()->assertSee('Judul rahasia');
        $this->patch('/reminders/'.$notification->id.'/read')->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs(User::factory()->create())->patch('/reminders/'.$notification->id.'/read')->assertNotFound();
        $meeting->participants()->delete();
        $this->actingAs($user)->get('/reminders')->assertOk()->assertDontSee('Judul rahasia')->assertSee('Pengingat tidak lagi aktif');
    }
}
