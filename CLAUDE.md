# Bosun — Authoring Guide

This is the authoritative guide for the pressgang-bosun codebase.

## What Bosun Is

A WP-CLI package (like Capstan) that composes AI agent guideline files for
PressGang child themes from Markdown fragments, selected by what the theme
actually has installed and enabled. Detection is pure filesystem/JSON
analysis (`composer.lock`, `config/*.php` presence and contents) — no
WordPress boot required, so the core stays unit-testable with plain PHPUnit.

## Design Rules

- Detection must never execute theme code — read files, don't require them.
- Fragments are data: plain Markdown, no templating (revisit only with a
  concrete need; Boost uses Blade, we start simpler).
- Fragment identity is the relative path (`{slug}/{path}.md`); later tiers
  override earlier tiers by identity. Tier order: package-shipped →
  bosun built-ins → theme-local `.ai/guidelines`.
- Package-shipped fragments live at `resources/boost/guidelines/**` in each
  package — Laravel Boost's third-party convention, adopted deliberately for
  cross-ecosystem familiarity.
- Bosun targets modern PressGang: fragments teach agents to use the
  framework's current conventions well, they are not legacy documentation.
  Conditionality comes from feature gates, not version directories.
- Feature-gated built-ins are included only when `ThemeInventory` detected
  the opt-in. Gate by fragment basename (see `FragmentLocator::applies()`).
- The composed document always opens with the inventory summary: agents must
  see versions/refs/opt-ins before conventions.
- Generated files are disposable: regeneration is idempotent; never merge
  with hand edits (theme-local fragments are the customisation point).

## Structure

- `bosun.php` — WP-CLI command registration (guarded on WP_CLI).
- `src/Commands/` — thin WP-CLI command wrappers; logic stays in services.
- `src/Detect/ThemeInventory.php` — lock + config analysis.
- `src/Guidelines/` — FragmentLocator (tiers), GuidelineComposer (document).
- `src/Agents/AgentTargets.php` — agent key → output file map.
- `resources/guidelines/` — built-in fragments by package slug.

## Adding a Fragment

1. Place it under `resources/guidelines/{slug}/` (slug = package name minus
   vendor and `pressgang-` prefix: pressgang, quartermaster, snippets).
2. Keep it concise and actionable: what the package is, its conventions,
   short code examples. Write for an agent that has never seen the fleet.
3. If the guidance only applies behind an opt-in, name the file after the
   feature key (e.g. `template-routing.md`) and add the key to
   `FragmentLocator::applies()` and `ThemeInventory::detect_features()`.
4. Add or update a locator/composer test.

## Testing

Plain PHPUnit 11 against `tests/fixtures/theme` (a miniature child theme
with a lock file, config opt-ins, a vendor-shipped fragment, and `.ai`
overrides). Run with `composer test`.
