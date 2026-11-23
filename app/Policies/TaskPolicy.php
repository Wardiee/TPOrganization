<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Who can see and manage tasks.
 *
 * v1 has one account (the admin), so every check passes for a signed-in user. Screens and actions
 * already ask these questions, so a read-only officer role later only needs changes here.
 */
class TaskPolicy
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
     * Mark done or not done.
     */
    public function update(User $user, Task $task): bool
    {
        return true;
    }

    /**
     * Only tasks the admin added can be deleted; automatic tasks close themselves.
     */
    public function delete(User $user, Task $task): bool
    {
        return ! $task->is_auto;
    }
}
