<?php

namespace App\Support;

use Illuminate\Support\Facades\Request;

/**
 * The app's navigation: the desktop sidebar and the phone tab bar.
 *
 * Each item lists the route names that make it the current item, so a
 * screen under More (e.g. Tasks) keeps "More" highlighted on phones.
 */
class Navigation
{
    /**
     * Desktop sidebar items, in display order.
     *
     * @return list<array{label: string, route: string, icon: string, active: list<string>}>
     */
    public static function sidebar(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard', 'active' => ['dashboard']],
            ['label' => 'Members', 'route' => 'members.index', 'icon' => 'members', 'active' => ['members.*']],
            ['label' => 'Org Structure', 'route' => 'org-structure', 'icon' => 'org', 'active' => ['org-structure']],
            ['label' => 'Activities & Attendance', 'route' => 'activities', 'icon' => 'activities', 'active' => ['activities']],
            ['label' => 'Birthdays & Dates', 'route' => 'birthdays', 'icon' => 'birthdays', 'active' => ['birthdays']],
            ['label' => 'Tasks', 'route' => 'tasks', 'icon' => 'tasks', 'active' => ['tasks']],
            ['label' => 'Documents & Photos', 'route' => 'documents', 'icon' => 'documents', 'active' => ['documents']],
            ['label' => 'Reports', 'route' => 'reports', 'icon' => 'reports', 'active' => ['reports']],
        ];
    }

    /**
     * Phone tab bar items, in display order.
     *
     * @return list<array{label: string, route: string, icon: string, active: list<string>}>
     */
    public static function tabs(): array
    {
        return [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'dashboard', 'active' => ['dashboard']],
            ['label' => 'Members', 'route' => 'members.index', 'icon' => 'members', 'active' => ['members.*']],
            ['label' => 'Attendance', 'route' => 'activities', 'icon' => 'activities', 'active' => ['activities']],
            ['label' => 'Birthdays', 'route' => 'birthdays', 'icon' => 'birthdays', 'active' => ['birthdays']],
            ['label' => 'More', 'route' => 'more', 'icon' => 'more', 'active' => [
                'more', 'org-structure', 'tasks', 'documents', 'reports', 'profile.edit', 'security.edit',
            ]],
        ];
    }

    /**
     * Whether the current request's route makes this item the current one.
     *
     * @param  array{active: list<string>}  $item
     */
    public static function isActive(array $item): bool
    {
        return Request::routeIs(...$item['active']);
    }
}
