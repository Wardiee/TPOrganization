# Start here: build Tropang Potchi with Claude Code in VS Code

This kit holds everything Claude Code needs to build the app: project instructions (`CLAUDE.md`), the build spec (`docs/SPEC.md`), your approved designs (`docs/design/`), your real member data for seeding (`database/seeders/data/`), the logo, and the prompts to paste, phase by phase (`docs/PROMPTS.md`).

Stack: Laravel 13 + Livewire 4 (official starter kit) + Tailwind. One app that works on desktop and installs on phones from the browser.

## 1. Install the tools (once)

1. **Laravel Herd** (herd.laravel.com) — gives you PHP, Composer and the `laravel` command in one install, on Windows or Mac. (If you already have PHP 8.3+, Composer and the Laravel installer, skip this.)
2. **Node.js LTS** (nodejs.org).
3. **VS Code**, then the **Claude Code** extension from the VS Code Extensions panel. Sign in when it asks.
4. Git, if you don't have it (git-scm.com).

Check in a terminal: `php -v` (8.3 or newer), `composer -V`, `node -v`, `laravel --version`.

## 2. Create the Laravel project

```bash
laravel new tropang-potchi
```

When it asks, choose:
- Starter kit: **Livewire**
- Authentication: **Laravel's built-in authentication**
- Testing framework: **Pest**
- Database: **SQLite**, and let it run the migrations
- Run `npm install` and build: **Yes**

The questions can change slightly between installer versions; pick the closest match.

## 3. Copy this kit into the project

Copy everything inside this `tropang-potchi-kit` folder into the new `tropang-potchi` folder, merging folders (`database`, `public` and `docs` already exist or will be created). Nothing in the kit overwrites a Laravel file except adding `CLAUDE.md`.

You can delete `START-HERE.md` from the project afterwards, or keep it.

## 4. Give Claude Code Laravel know-how (Laravel Boost)

In a terminal inside the project folder:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Choose **Claude Code** when it asks which agents/editors you use. Boost adds Laravel documentation search and project tools for Claude Code. It writes its own section into `CLAUDE.md` and keeps our project instructions outside that section.

## 5. Start building

1. Open the project in VS Code: `code .` (or File › Open Folder).
2. Start the app in the VS Code terminal: `composer run dev`, then open the address it prints (with Herd it's usually http://tropang-potchi.test).
3. Open Claude Code (the Claude icon in the sidebar).
4. Open `docs/PROMPTS.md` and paste **Phase 0**. Answer its questions, then continue with Phase 1, 2, 3…

Before Phase 1, set your login in `.env`:

```
ADMIN_NAME="Edward J. Dugan"
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=choose-a-strong-password
```

## Good habits

- One phase at a time. Check it in the browser at desktop and phone width before moving on.
- Let Claude Code commit at the end of each phase (`git init` first if the project isn't a git repo yet), so you can always go back.
- Your member data includes minors. Keep the app behind login, keep the GitHub repo **private** (the seed files in `database/seeders/data/` hold real names and birthdays), never commit `.env`, and use HTTPS when you put it online.
