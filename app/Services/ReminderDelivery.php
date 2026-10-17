<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Reminder;
use App\Notifications\MeetingReminder;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReminderDelivery
{
    public function schedule(Meeting $meeting): void
    {
        $meeting->reminders()->where('status', 'pending')->update(['status' => 'cancelled']);
        if ($meeting->status !== 'scheduled' || $meeting->starts_at->isPast()) {
            return;
        }
        foreach ($meeting->participants()->get() as $participant) {
            if ($meeting->reminders()->where('user_id', $participant->user_id)->where('meeting_version', $meeting->version)->where('status', 'sent')->exists()) {
                continue;
            }
            if ($participant->reminder_preference === 'none' || $participant->attendance_status === 'declined') {
                continue;
            }
            $scheduledAt = $participant->reminder_preference === 'morning'
                ? $meeting->starts_at->copy()->startOfDay()->setTime(7, 0)
                : $meeting->starts_at->copy()->subDay();
            if ($scheduledAt->gte($meeting->starts_at)) {
                $scheduledAt = $meeting->starts_at->copy()->subDay();
            }
            if ($scheduledAt->isPast()) {
                $scheduledAt = now();
            }
            $reminder = $meeting->reminders()->firstOrCreate([
                'user_id' => $participant->user_id,
                'meeting_version' => $meeting->version,
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            ]);
            if ($reminder->status === 'cancelled') {
                $reminder->update(['status' => 'pending']);
            }
        }
    }

    public function deliverDue(): int
    {
        $sent = 0;
        Reminder::where('status', 'pending')->where('scheduled_at', '<=', now())->orderBy('id')
            ->chunkById(100, function ($reminders) use (&$sent): void {
                foreach ($reminders as $candidate) {
                    try {
                        $sent += DB::transaction(function () use ($candidate): int {
                            $meeting = Meeting::whereKey($candidate->meeting_id)->lockForUpdate()->firstOrFail();
                            $reminder = Reminder::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                            if ($reminder->status !== 'pending' || $reminder->scheduled_at->isFuture()) {
                                return 0;
                            }
                            $eligible = $meeting->status === 'scheduled' && $meeting->starts_at->isFuture()
                                && $meeting->version === $reminder->meeting_version
                                && Participant::where('meeting_id', $meeting->id)->where('user_id', $reminder->user_id)
                                    ->where('attendance_status', '!=', 'declined')->where('reminder_preference', '!=', 'none')->exists();
                            if (! $eligible) {
                                $reminder->update(['status' => 'skipped']);

                                return 0;
                            }
                            $reminder->user->notify(new MeetingReminder($meeting));
                            $reminder->update(['status' => 'sent', 'sent_at' => now()]);

                            return 1;
                        }, attempts: 5);
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            });

        return $sent;
    }
}
