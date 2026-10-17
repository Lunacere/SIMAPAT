<?php

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('meeting-chat.{meetingId}.user.{userId}', function (User $user, int $meetingId, int $userId): bool {
    $meeting = Meeting::find($meetingId);

    return $user->id === $userId && $meeting !== null && $user->can('chat', $meeting);
});
