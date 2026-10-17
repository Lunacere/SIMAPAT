<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        return Meeting::visibleTo($user)->whereKey($meeting->id)->exists();
    }

    public function negotiate(User $user, Meeting $meeting): bool
    {
        return $meeting->client_id !== null && ($user->role === 'admin' || $this->update($user, $meeting)
            || ($user->role === 'client' && $user->client_id === $meeting->client_id));
    }

    public function chat(User $user, Meeting $meeting): bool
    {
        return $meeting->client_id !== null && ($this->update($user, $meeting)
            || ($user->role === 'client' && $user->client_id === $meeting->client_id));
    }

    public function viewNegotiation(User $user, Meeting $meeting): bool
    {
        return $meeting->client_id !== null && ($user->role === 'admin' || $this->negotiate($user, $meeting));
    }

    public function create(User $user): bool
    {
        return $user->role === 'operator';
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $user->role === 'operator' && $meeting->creator_id === $user->id;
    }
}
