<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the admin account from the ADMIN_* settings in .env.
     *
     * An existing admin is left untouched, so re-seeding never resets a
     * password the admin has since changed.
     */
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.');
        }

        $user = User::firstOrNew(['email' => $email]);

        if ($user->exists) {
            return;
        }

        $user->forceFill([
            'name' => config('admin.name'),
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();
    }
}
