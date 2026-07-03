# 🧭 Bosun

WP-CLI package that composes AI agent guidelines for PressGang WordPress
themes.

Aboard ship, the bosun pipes the captain's orders to the crew. Aboard your
project, Bosun pipes PressGang's conventions to the AI crew — so every
agent that comes aboard already knows the ropes.

## 🤔 Why

PressGang's best features are its most hidden: convention-based template
routing, context getter manifests, config-driven registration, the
Quartermaster fluent query builder, a library of ready-made snippets. By
design they leave barely a trace in a child theme's code — wonderful for
developers who know the ship, invisible to agents who don't. Left
unbriefed, an agent will write stub files, hand-rolled `WP_Query` arrays,
and `functions.php` hooks — working code that misses everything that makes
PressGang worth sailing.

Bosun is the briefing. Like Laravel Boost for the Laravel ecosystem, it
gives agents the context to use the framework *at its best* — composed from
what the theme actually has: package versions and source refs from
`composer.lock`, feature opt-ins from `config/`. Guidance for a feature
only comes aboard when the theme has opted in, so no agent gets told about
rigging the ship doesn't carry.

## 📦 Installation

Install as a global WP-CLI package — one install pipes orders to every
theme on the machine:

```bash
wp package install https://github.com/pressgang-wp/pressgang-bosun.git
```

## 🚀 Usage

From a PressGang project:

```bash
wp bosun install                # compose CLAUDE.md + AGENTS.md in the active child theme
wp bosun install --agents=claude
wp bosun update                 # recompose (idempotent) — add to composer post-update-cmd
```

That's it — all hands briefed.

Bosun never clobbers a hand-written file: existing `CLAUDE.md`/`AGENTS.md`
files without the bosun marker are skipped (pass `--force` to take them
over). Commit the generated files — agents on machines without Bosun still
get the briefing — and put customisations in `.ai/guidelines/`, never in
the generated output.

## 🧩 How Fragments Are Sourced

Fragments are plain Markdown, mustered in three tiers (later tiers override
earlier ones by matching relative path):

1. **📦 Package-shipped** — `vendor/{package}/resources/boost/guidelines/**.md`.
   The same third-party convention Laravel Boost defined, so package authors
   write fragments once for any ecosystem.
2. **⚓ Bosun built-ins** — `resources/guidelines/{slug}/**.md` in this
   package, covering pressgang, quartermaster, and snippets until those
   packages ship their own. Feature-gated fragments (e.g.
   `pressgang/v2/template-routing.md`) come aboard only when the theme's
   config opts in.
3. **🏠 Theme-local** — `{theme}/.ai/guidelines/**.md` for house rules, or
   overrides when the path matches an earlier fragment
   (e.g. `.ai/guidelines/pressgang/core.md`).

The composed document opens with an inventory summary — installed packages
with lock refs, and detected opt-ins — so agents reason about the theme's
reality, not the ecosystem's newest ideas.

## 🗺️ Roadmap

- **Phase 1 (you are here)** — guidelines composition; move package guidance
  in pressgang, quartermaster, and snippets into shipped fragments.
- **Phase 2** — skills distribution: packages ship
  `resources/boost/skills/{name}/SKILL.md`; Bosun installs them per detected
  package (absorbing the b-team migration skills plugin).
- **Phase 3** — introspection commands in
  [Capstan](https://github.com/pressgang-wp/pressgang-capstan):
  `wp capstan resolve <url>` (template candidates → controller),
  `capstan config`, `capstan snippets`, `capstan context <Controller>`;
  Bosun fragments teach agents the WP-CLI/Capstan recipes.
- **Phase 4** — local-first docs index: standardise Quartermaster's
  `api-index.json` generator across packages and aggregate per theme.
- **Phase 5 (if the tide demands)** — a thin MCP server proxying
  Capstan/WP-CLI.

## 🧪 Testing

```bash
composer install
composer test
```

Shipshape and Bristol fashion. ⚓
