<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

/**
 * Who can see the org structure and change who holds a seat.
 *
 * v1 has one account (the admin), so every check passes for a signed-in user. Screens and actions
 * already ask these questions, so a read-only officer role later only needs changes here.
 */
class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Add a new seat to a branch.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Assign, reassign or empty a seat.
     */
    public function update(User $user, Position $position): bool
    {
        return true;
    }
}
