<?php

use App\Models\Member;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;

dataset('app pages', [
    'dashboard' => ['dashboard'],
    'members' => ['members.index'],
    'add member' => ['members.create'],
    'member profile' => ['members.show', fn () => ['member' => Member::factory()->create()->id]],
    'edit member' => ['members.edit', fn () => ['member' => Member::factory()->create()->id]],
    'org structure' => ['org-structure'],
    'activities' => ['activities'],
    'birthdays' => ['birthdays'],
    'tasks' => ['tasks'],
    'documents' => ['documents'],
    'reports' => ['reports'],
    'more' => ['more'],
    'account settings' => ['profile.edit'],
    'appearance settings' => ['appearance.edit'],
]);

/**
 * The hrefs of all navigation links marked aria-current="page".
 *
 * @return list<string>
 */
function currentNavLinks(string $html): array
{
    preg_match_all('/<a\s+href="([^"]+)"\s+wire:navigate\s+aria-current="page"/', $html, $matches);

    return $matches[1];
}

test('guests are redirected to the login page', function (string $routeName, array $parameters = []) {
    $this->get(route($routeName, $parameters))->assertRedirect(route('login'));
})->with('app pages');

test('the seeded admin can sign in and open the page', function (string $routeName, array $parameters = []) {
    config(['admin.email' => 'chairman@example.com', 'admin.password' => 'a-strong-password']);
    $this->seed(AdminUserSeeder::class);

    $this->post(route('login.store'), [
        'email' => 'chairman@example.com',
        'password' => 'a-strong-password',
    ])->assertRedirect('/');

    $this->get(route($routeName, $parameters))->assertOk();
})->with('app pages');

test('the current page is marked in the sidebar and the tab bar', function (string $routeName, string $sidebarRoute, string $tabRoute) {
    $this->actingAs(User::factory()->create());

    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect(currentNavLinks($html))->toBe([route($sidebarRoute), route($tabRoute)]);
})->with([
    'dashboard' => ['dashboard', 'dashboard', 'dashboard'],
    'add member keeps Members current' => ['members.create', 'members.index', 'members.index'],
    'activities is Attendance on phones' => ['activities', 'activities', 'activities'],
    'tasks keeps More current on phones' => ['tasks', 'tasks', 'more'],
    'org structure keeps More current on phones' => ['org-structure', 'org-structure', 'more'],
]);

test('account settings mark the user card and More as current', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->get(route('profile.edit'))->assertOk()->getContent();

    expect(currentNavLinks($html))->toBe([route('profile.edit'), route('more')]);
});
