<?php

namespace App\Events;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ChatMessageCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public int $tries = 3;

    public function __construct(public int $meetingId, public int $messageId) {}

    public function broadcastOn(): array
    {
        $meeting = Meeting::findOrFail($this->meetingId);
        $accounts = User::where(function ($query) use ($meeting): void {
            $query->where('id', $meeting->creator_id)->where('role', 'operator');
            $query->orWhere(fn ($query) => $query->where('role', 'client')->where('client_id', $meeting->client_id));
        })->get();

        return $accounts->filter(fn (User $user): bool => $user->can('chat', $meeting))
            ->map(fn (User $user): PrivateChannel => new PrivateChannel('meeting-chat.'.$meeting->id.'.user.'.$user->id))->all();
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return ['message_id' => $this->messageId];
    }
}
