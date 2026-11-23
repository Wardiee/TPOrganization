# Tropang Potchi Management System

Admin app for the Tropang Potchi Brotherhood (founded 2020, "Where memories are made"). The Founder/Chairman uses it to keep member records, the org structure, activities and attendance, birthdays, tasks and files. One Laravel codebase serves both the desktop web layout and a phone layout that installs to the home screen (PWA).

Read `docs/SPEC.md` before starting any feature. Read the matching design file in `docs/design/` before building or changing any screen.

## Stack

- Laravel 13, PHP 8.3+, Livewire 4 (official Livewire starter kit), Tailwind CSS 4, Alpine (bundled with Livewire)
- Auth: the starter kit's built-in auth. Public registration is OFF. The admin account comes from a seeder using `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` in `.env`.
- Database: SQLite locally; keep migrations MySQL-compatible for hosting.
- Tests: Pest. Every feature gets feature tests; run `php artisan test` before saying a step is done.
- Code style: run `vendor/bin/pint --dirty` before finishing a step.

## How to build screens

- One full-page Livewire component per screen (see the route table in `docs/SPEC.md`).
- Each screen is RESPONSIVE, not duplicated: at `lg` (1024px) and up it uses the web design (left sidebar); below `lg` it uses the mobile design (top bar + bottom tab bar). Build the layout once in `resources/views/layouts/app.blade.php` (or the starter kit's equivalent) and switch with Tailwind breakpoints.
- Match the designs closely: spacing, type sizes, colors, radii, copy. Where web and mobile differ (e.g. attendance segmented control "Present/Excused/Absent" vs "P/E/A"), follow each at its breakpoint.
- Make small reusable Blade components for repeated pieces: `x-card`, `x-stat-tile`, `x-status-pill`, `x-tag`, `x-avatar-initials`, `x-date-badge`, `x-segmented`, `x-tab-bar`, `x-sidebar`. Flux components are fine for form inputs, modals and dropdowns if restyled to these tokens; don't let Flux's default look override the design.
- Accessibility is part of done: real `<button>`/`<a>`/`<label>`, touch targets at least 44px, visible focus, `aria-current` on the active nav item, `aria-pressed` on toggle buttons, text contrast 4.5:1.
- Icons: inline stroke SVGs as in the designs (2px stroke, round caps). No emoji.

## Design tokens (put these in the Tailwind theme in `resources/css/app.css`)

| Token | Value | Use |
|---|---|---|
| navy | #1C2566 | sidebar, mobile header, selected chips, primary dark buttons |
| navy-2 | #27317A | cards on navy, secondary buttons on navy |
| yellow | #F5D547 | active nav pill, accents, avatar on navy |
| blue | #3446B0 | primary buttons, links, chart bars, "present" |
| bg | #F6F5EF | page background |
| line | #E4E2D8 | card borders; #F0EEE6 for row dividers; #D6D3C6 for input borders |
| ink | #16181D | text |
| muted | #5C6070 | secondary text |
| active pill | bg #DFF3E6 / text #17603A | |
| floating pill | bg #FFF0CC / text #7A4A00 | also "MISSING" tag |
| inactive pill | bg #ECEBE6 / text #4A4A4A | |
| alert tag | bg #FDE3D3 / text #8A3B12 | VACANT, CONFLICT, High priority |
| info tag | bg #E3E7FA / text #27317A | UNASSIGNED, Members category |
| absent | #B5471B | attendance "absent" |

Fonts (Google Fonts): **Archivo** 700/800 for headings, big numbers and the wordmark; **Inter** 400–700 for everything else. Cards: white, 1px line border, 16px radius. Pills: fully rounded.

## Rules

- Members include minors. Every page needs login. Uploaded files go on a private disk and are served only through an authorized route. Never expose member data publicly or log it.
- Birthdays are stored as month + day + nullable year, because many members have no birth year recorded. Ages are calculated, never stored. Show "birth year missing" instead of an age when the year is null.
- Activities may have no name and/or no date (real data has both). Show "Untitled activity" / "Date not recorded" and flag them on the dashboard.
- Keep history: never delete position assignments or status changes; end them with a date. The org chart can be viewed "Today" or as the published "Org Chart 2024" snapshot.
- Seed data lives in `database/seeders/data/*.json` (real records exported from the founder's spreadsheet). Seeders must read these files; don't retype the data.
- Dates shown like "Dec 30, 2025"; the app's timezone is Asia/Manila.
- Don't add packages without saying why. Prefer what Laravel and Livewire already provide.
- Work in the phases in `docs/PROMPTS.md`. Finish, test, and summarize one phase before starting the next. Commit at the end of each phase with a clear message.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

@AGENTS.md
