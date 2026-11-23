<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

/**
 * Who can see and change member records.
 *
 * v1 has one account, the admin, so every check passes for a signed-in user. Screens and actions
 * already ask these questions, so a read-only officer role later only needs changes here.
 */
class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Member $member): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Member $member): bool
    {
        return true;
    }

    /**
     * Private notes are for the admin only.
     */
    public function viewPrivateNotes(User $user, Member $member): bool
    {
        return true;
    }
}
