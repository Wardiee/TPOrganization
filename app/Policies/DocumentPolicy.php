<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Who can see, download and manage files. Files can include photos of minors, so every download and
 * preview goes through this check.
 *
 * v1 has one account (the admin), so every check passes for a signed-in user. Screens and actions
 * already ask these questions, so a read-only officer role later only needs changes here.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Preview or download the file.
     */
    public function view(User $user, Document $document): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Rename, move or relink.
     */
    public function update(User $user, Document $document): bool
    {
        return true;
    }

    public function delete(User $user, Document $document): bool
    {
        return true;
    }
}
