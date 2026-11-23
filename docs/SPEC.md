# Tropang Potchi Management System — Build spec

## 1. Who uses it

- **Admin** (v1): the Founder/Chairman, Edward J. Dugan. Full access. Created by `AdminUserSeeder` from `.env`.
- Later (not v1): officers with view-only access. Design the policies so a read-only role can be added without rewriting screens (use Gates/Policies, not `if ($user->id === 1)`).

## 2. Data model

| Table | Key columns | Notes |
|---|---|---|
| `chapters` | name | Pag-asa, Villariez, Dalsa, Poblacion, Davao |
| `members` | member_no (nullable int), full_name, nickname, birth_month, birth_day, birth_year (nullable), grade_level, chapter_id, status (`active`/`floating`/`inactive`), status_changed_on, remarks, joined_on (nullable), private_notes, photo_path, timestamps, soft deletes | Accessors: `age`, `initials`, `birthday_label`, `missing_fields` (array of: Nickname, Birthday, Birth year, Grade, Position) |
| `member_status_changes` | member_id, from_status, to_status, changed_on, note | Written whenever status changes |
| `positions` | code (FOUNDER, CO-FOUNDER, TROP-1…15, TROPS-1…15), title, branch (`TROP`/`TROPS`/null), sort | Seeded |
| `position_assignments` | position_id, member_id (nullable), name_as_listed (nullable), started_on, ended_on (nullable), source | Current holder = row with `ended_on` null. Never delete; end and add a new row |
| `org_charts` | title, published_on, updated_on | Published snapshots ("Org Chart 2024") |
| `org_chart_seats` | org_chart_id, position_id, member_id (nullable), name_as_listed | Snapshot rows; name as printed on the chart |
| `activities` | name (nullable), starts_on (nullable), ends_on (nullable), location, notes | |
| `attendances` | activity_id, member_id, status (`present`/`excused`/`absent`) | Unique (activity_id, member_id). No row = absent |
| `tasks` | title, detail, category (`Members`/`Structure`/`Activities`/`Birthdays`/`Records`), priority (`High`/`Medium`/`Low`/null), due_on, link_route, source_key (nullable, unique), is_auto, completed_at | |
| `folders` | name | Member Records, Org Charts, Branding & Logos, Activity Photos, Member Documents |
| `documents` | folder_id, member_id (nullable), activity_id (nullable), original_name, path, mime, size, note | Private disk |
| `history_events` | happened_on, date_precision (`day`/`year`), title, detail, kind (`org`/`members`/`activity`) | Org history timeline + "Recent changes" |

### Seeding (`database/seeders/data/`)

- `chapters.json`, `positions.json`, `folders.json`, `history.json`: seed as-is.
- `members.json`: 42 members. `current_position` (e.g. `TROP-9`) creates an open `position_assignments` row. For inactive position holders (TROP-13, TROP-14, TROPS-14, TROPS-15) keep the assignment open, because "held by an inactive member" is an issue the dashboard flags.
- `activities.json` + `attendance.json`: 6 activities, 77 non-absent attendance rows (present/excused). Absent = no row. Totals must come out as: Leiana Outing 3 present + 6 excused, Graduation 12, Oct 6 2024 17, undated 15, Wake 15, Year-End Bonding 9.
- `org-chart-2024.json`: the published snapshot (32 seats). Note TROP-3 is listed as "Jan Griz C. Tulingin" on the chart but "Jan Griz Cartagena" in the members list; keep both, because that mismatch is a dashboard issue.
- `original-files.json`: just a list of the founder's source files, for reference. The real files aren't included; don't seed documents from it.
- The founder (Edward J. Dugan) is not in the members list. Treat him as the admin user and the FOUNDER seat on the chart.

## 3. Screens and routes

Web design = `docs/design/web/*.dc.html` (desktop, ≥1024px). Mobile design = `docs/design/mobile/*.dc.html` (phone, <1024px).

| Route | Screen | Web design | Mobile design |
|---|---|---|---|
| `/` | Dashboard | `web/Main` | `mobile/Home` |
| `/members` | Members directory | `web/Members` | `mobile/Members` |
| `/members/create`, `/members/{member}/edit` | Add / edit member | (form; follow the profile's field layout) | (full-screen form) |
| `/members/{member}` | Member profile | `web/Profile` | `mobile/Profile` |
| `/org-structure` | Org structure & history | `web/OrgChart` | `mobile/OrgChart` |
| `/activities` | Activities & attendance | `web/Activities` | `mobile/Attendance` |
| `/birthdays` | Birthdays & dates calendar | `web/Birthdays` | `mobile/Calendar` |
| `/tasks` | Tasks | `web/Tasks` | `mobile/Tasks` |
| `/documents` | Documents & photos | `web/Documents` | `mobile/Documents` |
| `/more` | More menu (phone only; redirect to `/` at ≥1024px or just hide its link) | — | `mobile/More` |
| `/reports` | Reports | not designed yet: placeholder page saying "Coming soon" | "Soon" label in More |

**Navigation.** Web: left sidebar (Dashboard, Members, Org Structure, Activities & Attendance, Birthdays & Dates, Tasks, Documents & Photos, Reports) with the user card at the bottom. Mobile: bottom tab bar (Home, Members, Attendance, Birthdays, More). Org Structure, Tasks and Documents sit under More, and those screens keep "More" highlighted in the tab bar and show a "‹ More" back link.

### Screen behavior

**Dashboard.** Greeting by time of day ("Good afternoon, Chairman") and today's date. Quick actions: Take attendance, New activity, Add member. Stat tiles: member counts by status; last activity turnout (present ÷ active members); birthdays in the next 30 days with the next one named; profiles complete (no missing fields) as a count and percentage bar. Attendance per activity bar chart (latest activity highlighted yellow). Upcoming birthdays (next 4). "Needs your attention" from `RecordChecks` (below). Members by chapter bars. Recent changes from `history_events`.

**Members.** Status tabs/chips with counts (All, Active, Floating, Inactive); live search across name, nickname and position; web also has a chapter filter and a "missing info only" toggle (see design). Each row: initials avatar, name, nickname · position · chapter, status pill, "Missing: …" note, and on web the 6-dot attendance strip. Row opens the profile.

**Member profile.** Header with initials, name, nickname, chapter, member no., status and position pills. Actions: Edit, Change position. Personal info (birthday, auto age, grade, chapter, position, date joined with "Add date"). Attendance (X of N with per-activity list). Position & record history timeline. Private notes (only the admin sees them). Documents & photos for this member with upload.

**Org structure.** Toggle "Today" vs "Org Chart 2024" (read-only snapshot). Founder card, then TROP (Main) and TROPS (Ext) branches, each with its lead and seats. Today view flags seats held by inactive members ("Reassign") and name mismatches ("Review"). "No position yet" list of active members without a seat, with Assign. History timeline. Reassign/Assign opens a modal: choose member + start date; this ends the old assignment and opens a new one, and writes a history event.

**Activities & attendance.** Activity list (newest first) with present counts; unnamed ones show "Untitled · needs a name". Two views on web: "Take attendance" (one row per active member with Present / Excused / Absent segmented control, totals, "Mark all present") and "Overview grid" (members × activities). Mobile: horizontal activity chips, totals tiles, P/E/A segmented control per row, sticky "Save attendance · N% present" button. New activity: name (optional), date or date range (optional), location, notes. Changes save per tap (Livewire), and the Save button confirms.

**Birthdays & dates.** Month calendar with prev/next/Today. Web: chips inside day cells; "show inactive members" toggle. Mobile: dots under day numbers (yellow birthday, blue activity, teal org update); tapping a day lists its items below. "Next 60 days" list with "turns N" or "birth year missing".

**Tasks.** Filters Open / All / Completed. Groups: "Coming up" (dated, sorted) and "No due date" (web) / "Anytime" (mobile), then Completed. Each task: checkbox, title, detail, category tag, priority tag, due date, link to the related screen. Add task (title, category, due date). Auto tasks come from `tasks:sync` (below); a completed auto task stays completed even if its check still fires.

**Documents & photos.** Folder tiles with counts; file list filtered by folder; selected-file detail panel on web; upload (web drag-and-drop; mobile "Take photo" uses `<input type="file" accept="image/*" capture="environment">` and "Upload file"). Files can be linked to a member or activity. Downloads go through an authorized route.

## 4. Record checks (dashboard "Needs your attention")

`App\Services\RecordChecks` returns a list of issues (`tag`, `title`, `detail`, `action label`, `route`). Checks:

1. **VACANT**: open position assignments whose member is inactive.
2. **CONFLICT**: a member's name differs from `name_as_listed` on the latest org chart for their seat.
3. **UNASSIGNED**: active members with no open position assignment.
4. **MISSING**: active members with no birth year, which includes those with no birthday at all (11 with the seed data: 8 have month/day only, 3 have nothing).
5. **MISSING**: activities with no name or no date.

With the seed data, the dashboard must show exactly the issues in `docs/design/web/Main.dc.html`. Write a test that asserts this.

## 5. Tasks sync

Artisan command `tasks:sync`, scheduled daily at 06:00 Asia/Manila. It creates or updates auto tasks (keyed by `source_key`) from the record checks, plus a "Birthday greeting: {name}" task for each active member with a birthday in the next 30 days (due on the birthday). It never reopens a task the admin completed.

## 6. Phone app (PWA)

- `public/manifest.webmanifest`: name "Tropang Potchi", short name "TP Admin", theme color #1C2566, background #F6F5EF, display `standalone`, start URL `/`, icons 192/512 plus a maskable icon, made from `public/images/logo.png`.
- Apple meta tags + `apple-touch-icon` so iPhone "Add to Home Screen" looks right.
- A small service worker: cache the built CSS/JS, fonts and logo; show an offline page when there's no connection. Do not cache member pages or API responses (private data on shared phones).
- Layout: respect safe areas (`env(safe-area-inset-bottom)` under the tab bar), no horizontal scrolling at 360px width.
- Install needs HTTPS in production.
- Optional, last phase: offline attendance. Queue P/E/A taps in IndexedDB/localStorage while offline and sync when back online, with a visible "3 changes waiting to sync" banner. The More screen's "Work offline" toggle controls it.

## 7. Out of scope for v1

Reports (placeholder only), Excel import (data comes from the seeders), multiple user roles, SMS/notifications beyond the in-app tasks.
