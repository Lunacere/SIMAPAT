<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, User $account): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, User $account): bool
    {
        return $user->role === 'admin' && $user->id !== $account->id;
    }
}
