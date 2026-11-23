<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

test('it creates the admin from the admin settings', function () {
    config([
        'admin.name' => 'Edward J. Dugan',
        'admin.email' => 'chairman@example.com',
        'admin.password' => 'a-strong-password',
    ]);

    $this->seed(AdminUserSeeder::class);

    $admin = User::sole();
    expect($admin->name)->toBe('Edward J. Dugan')
        ->and($admin->email)->toBe('chairman@example.com')
        ->and(Hash::check('a-strong-password', $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull();
});

test('re-seeding keeps a password the admin has changed', function () {
    config(['admin.email' => 'chairman@example.com', 'admin.password' => 'the-env-password']);
    $this->seed(AdminUserSeeder::class);
    User::sole()->update(['password' => 'changed-in-settings']);

    $this->seed(AdminUserSeeder::class);

    expect(User::count())->toBe(1)
        ->and(Hash::check('changed-in-settings', User::sole()->password))->toBeTrue();
});

test('it refuses to seed without an admin email and password', function (array $settings) {
    config($settings);

    $this->seed(AdminUserSeeder::class);
})->with([
    'no email' => [['admin.email' => null, 'admin.password' => 'a-strong-password']],
    'no password' => [['admin.email' => 'chairman@example.com', 'admin.password' => '']],
])->throws(RuntimeException::class, 'Set ADMIN_EMAIL and ADMIN_PASSWORD');
