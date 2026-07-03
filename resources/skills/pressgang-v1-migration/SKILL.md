---
name: pressgang-v1-migration
description: Migrate a PressGang v1 + Timber 1 child theme to PressGang 2, Timber 2, and Quartermaster, preserving behaviour exactly.
requires-feature: legacy-v1
---

# PressGang v1 → v2 Migration

## When to use this skill

This theme still boots through PressGang v1 (`core/settings.php` detected).
Use this skill to migrate it. Behaviour parity is the prime directive: port
bugs as-is and flag them; never change output while migrating.

The theme will be **non-functional from the composer phase until the Timber
phase completes** — the v2 parent replaces v1 entirely. Review phases by
code inspection; runtime testing resumes at validation.

## Phases (in order, each independently reviewable)

1. **Audit (read-only).** Inventory: `settings.php` sections, controllers
   and their base classes, `classes/` (Timber models/menus), `inc/` hooks,
   Timber v1 API call sites (PHP and Twig), ACF setup, custom routing.
   Derive the namespace from the `THEMENAME` constant value in StudlyCase.
2. **Composer.** Require `pressgang-wp/pressgang dev-master`,
   `pressgang-wp/pressgang-snippets dev-main`,
   `pressgang-wp/quartermaster dev-main`, ACF Pro `^6.6` as an mu-plugin
   (with an `mu-plugins/acf.php` loader — WordPress does not autoload
   subdirectories), PHPCS/WPCS dev tooling. `minimum-stability: dev`,
   `prefer-stable: true`.
3. **PSR-4.** Controllers → `src/Controllers/` (v2 base classes), shared
   query logic → `src/Traits/`, content models → `src/Models/`, menu
   classes → `src/Menu/`, `inc/` hooks → `src/Snippets/` (check the
   snippets library for 1:1 equivalents first). Do NOT create a QueryScopes
   trait — Quartermaster replaces that pattern.
4. **Config.** `settings.php` sections → `config/*.php` (menus, CPTs,
   taxonomies, support, acf-options, snippets, service-providers).
   `functions.php` reduces to the `THEMENAME` define.
5. **Timber 2.** Replace v1 APIs in PHP and Twig. Then Quartermaster:
   convert query arrays to fluent chains (`->toArray()` for truthiness-
   checked context values, `->timber()` for paginated listings).
6. **Validate.** Boot via `wp eval`; curl every route grepping for
   Fatal/Warning/Deprecated; compare rendered output against the live site
   route-by-route; remove legacy dirs and the old ACF plugin copy; then
   `wp bosun update` so guidelines reflect the modern theme.

## Known traps (each has broken a real migration)

- `new \Timber\PostQuery($args)` with an array **fatals** in Timber 2 —
  use `Timber::get_posts($args)` or Quartermaster.
- `Timber::get_posts()` returns a PostQuery: always truthy (breaks Twig
  `{% if %}`), and not an array (breaks `array_merge`). Use `->to_array()`.
- `numberposts` is ignored by Timber 2 — use `posts_per_page`.
- Timber's method `$post->get_field('x')` → `$post->meta('x')`; the ACF
  *function* `get_field('x', $id)` is fine — leave it.
- ACF relationship/post-object values arrive as raw WP_Post/IDs — convert
  with `PressGang\ACF\TimberMapper::to_timber_posts()`.
- Twig `|date()` on ACF date meta applies the WP timezone: pass `false` as
  the second argument or dates shift (check BST/DST edge dates).
- Legacy `acf_add_options_page()` slugs carry an `acf-options-` prefix;
  PressGang 2 registers the bare config key — update acf-json location
  rules to match.
- v1 `Timber\Term` objects were stringable; Timber 2's are not — pass
  `->slug` into `tax_query` terms.
- `$MenuItemClass` on `Timber\Menu` is gone — register MenuItem classes via
  the `Theme\MenuItemClassMap` library snippet, overriding `children()`
  (not `get_children()`).
- The base layout must call `{{ fn('wp_head') }}` / `{{ fn('wp_footer') }}`
  or enqueued assets never load.
- Scope every bulk search/replace to `views/` and `src/` — never `vendor/`
  or a pattern library.
