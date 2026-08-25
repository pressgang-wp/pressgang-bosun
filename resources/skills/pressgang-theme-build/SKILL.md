---
name: pressgang-theme-build
description: Build out a PressGang child theme from scratch or extend one with new content types, using the framework's conventions in the right order.
---

# PressGang Theme Build

## When to use this skill

Use when building a new PressGang child theme (e.g. scaffolded from
pressgang-child) or adding a content type / section to an existing one.
Follow the phases in order — each produces reviewable output the next
depends on. Consult the theme's composed guidelines (CLAUDE.md / AGENTS.md)
for the conventions this workflow assumes.

## Phase 1 — Understand the design surface

- If a `/patterns` pattern library exists, treat it as read-only reference:
  its templates define the markup and CSS classes the theme's `views/` must
  reproduce. Never modify `/patterns`.
- List the page types the design implies (home, listing, single, landing
  pages) and the content model behind them (post types, taxonomies, fields).

## Phase 2 — Declare the content model (config only)

- Custom post types in `config/custom-post-types.php`, taxonomies (with
  `object-type` + `args`) in `config/custom-taxonomies.php`, menus in
  `config/menus.php`, theme support in `config/support.php`.
- Post type and taxonomy keys use underscores (`event_type`) — they become
  query vars. Template and Twig names stay kebab-case; routing bridges the
  two automatically.
- ACF: field groups as JSON in `acf-json/`; options pages in
  `config/acf-options.php` (the key is the page slug).
- No registration code in `functions.php` — it defines `THEMENAME` only.

## Phase 3 — Models and menus (only if needed)

- Content models extending `Timber\Post` / `Timber\Term` go in
  `src/Models/`, registered in `config/timber-class-map.php`. Computed
  properties use the parent theme's `PressGang\Traits\HandlesDynamicGetters`.
- Menu classes (`Timber\MenuItem` subclasses, menu builders) go in
  `src/Menu/`, registered via the `Theme\MenuItemClassMap` library snippet.

## Phase 4 — Controllers and traits

- One controller per template concern, named for its route:
  plural for archives (`EventsController`), singular for singles
  (`EventController`), subject for taxonomies (`EventTypeController`),
  `{Slug}Controller` for page templates (`ContactPageController`).
  With template routing enabled these names ARE the routing — no stubs.
- Declare each controller's template contract in a documented
  `$context_getters` manifest; implement one getter per key with
  `??=` caching.
- Shared base queries live in `src/Traits/` as `Has{Domain}` traits
  returning Quartermaster builders (e.g. `HasEvents::upcoming_events()`);
  controllers compose them with their own limits/filters/terminals.
- Use `->toArray()` for context values Twig truth-tests; `->timber()` for
  paginated listings.

## Phase 5 — Views

- Twig templates in `views/` named after hierarchy candidates
  (`archive-event.twig`, `taxonomy-event-type.twig`) so routing finds them;
  partials under `views/partials/`.
- Port markup faithfully from the pattern library where one exists. Twig is
  presentation only.
- Register any Twig functions via an extension manager in
  `src/TwigExtensions/` listed in `config/twig-extensions.php` — never
  `add_filter('timber/twig', ...)` in a controller.

## Phase 6 — Behaviour

- Check the snippets library before writing hooks; enable via
  `config/snippets.php`. Custom snippets implement `SnippetInterface`, hooks
  registered in the constructor, one concern each.
- Custom URLs go in `config/routes.php` — a template name, or a
  `RouteHandlerInterface` class when the route needs logic (build its query
  args with Quartermaster's `->toArgs()`).

## Phase 7 — Verify

- If the theme provides `composer check`, run it first. It should be a narrow
  local-dev alias for `test:compat` plus `phpstan`; keep those as separate CI
  jobs when failure attribution matters.
- If `composer check` is missing, run the theme's documented test and static
  analysis commands separately. Prefer source/PHPDoc/stub/config fixes over
  PHPStan ignores, and do not add baselines unless explicitly requested.
- Boot check: `wp eval 'echo "ok";'` (loads the full theme).
- Route sweep: `curl` each page type; grep for `Fatal error`, `Warning:`,
  `Deprecated:` — all should be absent.
- Runtime introspection belongs to Capstan/Shakedown: use `wp capstan
  resolve`, `wp capstan context`, `wp capstan config dump`, and Shakedown's
  `wp capstan matrix --resolve` oracle rather than inventing new validation.
- `wp capstan doctor` is runtime-only and should not be treated as a PHPStan
  replacement.
- If replacing an existing site, compare rendered output against it
  route-by-route (nav counts, listing counts, dates — including BST/DST
  edge dates for event times).
- Regenerate agent guidelines after dependency changes: `wp bosun update`.
