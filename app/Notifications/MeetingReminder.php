<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Notifications\Notification;

class MeetingReminder extends Notification
{
    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['meeting_id' => $this->meeting->id, 'meeting_version' => $this->meeting->version];
    }
}
