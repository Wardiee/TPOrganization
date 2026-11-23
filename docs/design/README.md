# Design reference

These are the approved screen designs, exported from the design canvas. They're for reading, not for copying into the app as-is.

- `web/` — desktop layout (1440px wide, fluid). Use at the `lg` breakpoint and up.
- `mobile/` — phone layout (390 × 844). Use below `lg`.

## How to read a `.dc.html` file

Each file is one screen written in a small template format:

- Layout and styling are inline `style="…"` attributes. Translate them to Tailwind classes using the tokens in `CLAUDE.md`. Keep the same spacing, sizes, radii and colors.
- `{{name}}` is a value filled in from the `<script type="text/x-dc">` block at the bottom of the file. That script holds the sample data and the screen's behavior (filters, toggles, selected states). Read it to understand how the screen should behave, then rebuild that behavior with Livewire/Alpine and real database queries.
- `<sc-for list="{{items}}" as="item">` repeats its contents for each item, like `@foreach`.
- `<sc-if value="{{cond}}">` shows its contents only when the condition is true, like `@if`.
- `onClick="{{handler}}"` is a click handler, like `wire:click`.
- `href="Members.dc.html"` links between screens. Map these to the routes in `docs/SPEC.md`.
- The logo is at `/images/logo.png`.

The sample data in the scripts matches the real seed data in `database/seeders/data/`. The numbers on the dashboard design are what the app should show after seeding.
