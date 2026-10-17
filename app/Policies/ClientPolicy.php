<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'operator'], true);
    }

    public function create(User $user): bool
    {
        return $user->role === 'operator';
    }

    public function update(User $user, Client $client): bool
    {
        return $user->role === 'operator';
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->role === 'operator';
    }
}
