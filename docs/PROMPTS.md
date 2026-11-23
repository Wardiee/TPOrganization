# Prompts for Claude Code, in order

Paste one prompt at a time into Claude Code in VS Code. Let it finish, look at the result in your browser (desktop width and phone width), then paste the next. If something looks wrong, say so in plain words before moving on, e.g. "the bottom tab bar covers the last member row on my phone".

Tip: to see the phone layout on your computer, open the browser's developer tools (F12) and turn on the device toolbar (Ctrl+Shift+M), then pick a phone like Pixel 7 or iPhone 14.

---

## Phase 0 — Get oriented (no code yet)

```
Read CLAUDE.md, docs/SPEC.md, docs/design/README.md, and skim every file in docs/design/web and docs/design/mobile. Also look at the JSON files in database/seeders/data. Then give me: (1) a short summary of what we're building, (2) anything in the spec or data that looks inconsistent or unclear, as questions for me, and (3) your plan for Phase 1. Don't write code yet.
```

## Phase 1 — Foundation: look, layout, login

```
Phase 1. Set up the foundation:
- Add the design tokens and the Archivo + Inter fonts to the Tailwind theme.
- Turn off public registration. Add an AdminUserSeeder that creates the admin from ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD in .env (add these to .env.example).
- Build the app layout: the left sidebar from docs/design/web/Main.dc.html at lg and up, and the top bar + bottom tab bar from docs/design/mobile/Home.dc.html below lg. Active item highlighted on both. Respect the phone safe area under the tab bar.
- Restyle the login page with the logo, navy and yellow so it matches the app.
- Create empty placeholder pages for every route in docs/SPEC.md so all navigation works.
- Add feature tests: guests are redirected to login; the admin can log in and open every page.
Run the tests and pint, then tell me how to log in and what to check.
```

## Phase 2 — Database and real data

```
Phase 2. Build the data model from docs/SPEC.md section 2: migrations, models with relationships and the accessors listed (age, initials, birthday_label, missing_fields), factories, and seeders that read database/seeders/data/*.json. Add a DatabaseSeeder that runs everything, including the admin.
Write tests that check, after seeding: 42 members (29 active, 7 floating, 6 inactive); attendance totals per activity match the numbers in SPEC.md; TROP-9 is held by Jeffrey Oliver Gumobao; 11 active members are missing a birth year or birthday; the Org Chart 2024 snapshot lists TROP-3 as "Jan Griz C. Tulingin".
Run php artisan migrate:fresh --seed and the tests.
```

## Phase 3 — Members directory and profiles

```
Phase 3. Build /members, /members/{member}, and the add/edit member form, matching docs/design/web/Members.dc.html + Profile.dc.html on desktop and docs/design/mobile/Members.dc.html + Profile.dc.html on phones. Include status chips with counts, live search, the chapter filter and "missing info only" toggle (web), the attendance dots strip (web), "Missing: …" notes, private notes saving, and status changes writing to member_status_changes. Tests for search, filters, creating, editing and changing status.
```

## Phase 4 — Dashboard and record checks

```
Phase 4. Build the RecordChecks service from SPEC.md section 4, then the dashboard at / matching docs/design/web/Main.dc.html and docs/design/mobile/Home.dc.html. With seed data, the dashboard should show the same numbers and the same "Needs your attention" items as the design. Add a test asserting the exact issues. Use simple HTML/CSS bars for the charts (no chart library needed).
```

## Phase 5 — Activities and attendance

```
Phase 5. Build /activities matching docs/design/web/Activities.dc.html and docs/design/mobile/Attendance.dc.html: activity list, new/edit activity (name and date optional), "Take attendance" with Present/Excused/Absent per active member (P/E/A on phones), totals, "Mark all present", and the web "Overview grid". Each tap saves through Livewire. Make the phone version fast to use with one thumb. Tests for marking attendance and totals.
```

## Phase 6 — Org structure and history

```
Phase 6. Build /org-structure matching docs/design/web/OrgChart.dc.html and docs/design/mobile/OrgChart.dc.html: Today vs Org Chart 2024 toggle, branches with leads and seats, flags for inactive holders and name mismatches, the "No position yet" list, Assign/Reassign modal (ends the old assignment, opens a new one, writes a history event), and the history timeline. The member profile's "Change position" button should open the same modal. Tests for reassigning and for history being kept.
```

## Phase 7 — Birthdays and dates

```
Phase 7. Build /birthdays matching docs/design/web/Birthdays.dc.html and docs/design/mobile/Calendar.dc.html: month calendar with prev/next/Today, birthdays + activities + org updates on their days, the "show inactive" toggle (web), tap-a-day details (phone), and the "Next 60 days" list. Handle members with no birth year and Feb 29 birthdays. Tests for the upcoming list.
```

## Phase 8 — Tasks

```
Phase 8. Build /tasks matching docs/design/web/Tasks.dc.html and docs/design/mobile/Tasks.dc.html, plus the tasks:sync command and daily schedule from SPEC.md section 5. Run tasks:sync in the DatabaseSeeder so the seeded app has the same tasks as the design. Tests: sync creates the expected tasks, doesn't duplicate, and doesn't reopen completed ones.
```

## Phase 9 — Documents and photos

```
Phase 9. Build /documents matching docs/design/web/Documents.dc.html and docs/design/mobile/Documents.dc.html, plus the documents section on the member profile. Files go on a private disk; downloads and image previews go through an authorized route. On phones, "Take photo" opens the camera. Limit uploads to images, PDF, Word and Excel up to 20 MB. Tests for upload, download authorization and folder counts.
```

## Phase 10 — Install on phones (PWA)

```
Phase 10. Make the app installable on Android and iPhone as described in SPEC.md section 6: web manifest, icons generated from public/images/logo.png (192, 512, maskable, apple-touch-icon), iOS meta tags, a service worker that caches only static assets and shows an offline page, and safe-area handling. Then walk me through testing "Install app" on Android Chrome and "Add to Home Screen" on iPhone Safari, including what I need for HTTPS.
```

## Phase 11 — Polish and launch checklist

```
Phase 11. Review the whole app against the designs at 360px, 390px, 1024px and 1440px widths and fix differences. Check keyboard navigation, focus states, contrast and 44px touch targets. Make sure the whole test suite passes. Then write docs/DEPLOY.md: how to deploy to a host with HTTPS, run migrations and seeders once, set up the scheduler for tasks:sync, set up backups of the database and the private files, and change the admin password.
```

## Optional — Offline attendance

```
Optional phase. Add offline attendance from SPEC.md section 6: when the phone is offline on the attendance screen, queue P/E/A taps locally, show "N changes waiting to sync", and sync automatically when back online. Wire it to the "Work offline" toggle on the More screen. Explain how to test it with the browser's offline mode.
```
