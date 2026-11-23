<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

/**
 * Who can see activities, record attendance and change activity details.
 *
 * v1 has one account (the admin), so every check passes for a signed-in user. Screens and actions
 * already ask these questions, so a read-only officer role later only needs changes here.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Edit details and record attendance.
     */
    public function update(User $user, Activity $activity): bool
    {
        return true;
    }

    /**
     * Delete (soft) an activity, e.g. one added by mistake.
     */
    public function delete(User $user, Activity $activity): bool
    {
        return true;
    }
}
