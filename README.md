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

Bosun owns a marked region inside each file — everything between
`<!-- bosun:start -->` and `<!-- bosun:end -->` — never the whole file.
A hand-written `CLAUDE.md`/`AGENTS.md` keeps every byte of its content
and gains the region appended at the end; re-runs replace the region in
place. The only file Bosun refuses to touch is one whose markers are
unbalanced or duplicated (fix them by hand, or pass `--force` to rewrite
the file as region-only). Commit the generated files — agents on machines
without Bosun still get the briefing — and put customisations in
`.ai/guidelines/` or outside the region, never inside it.

## 🧩 How Fragments Are Sourced

Fragments are plain Markdown, mustered in three tiers (later tiers override
earlier ones by matching relative path):

1. **📦 Package-shipped** — `vendor/{package}/resources/bosun/guidelines/**.md`.
   The same shape as Laravel Boost's third-party convention, under Bosun's
   own namespace — the semantics layered on top (feature gating) are
   Bosun's, and no PressGang package will ever share a vendor directory
   with a Laravel app.
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

Packages may also ship a machine-readable API index at
`docs/api-index.json` (method signatures, the query args they set, links
to WordPress docs). Bosun doesn't copy it anywhere — the composed
guidelines point agents at the vendor file, the single source of truth.
Malformed or oversized indexes are skipped silently.

## 🗺️ Roadmap

- **Phase 1 (you are here)** — guidelines composition; move package guidance
  in pressgang, quartermaster, and snippets into shipped fragments.
- **Phase 2 (begun)** — skills distribution: packages ship
  `resources/bosun/skills/{name}/SKILL.md`; Bosun installs detected skills
  to `.claude/skills/` (override via `.ai/skills/{name}`). Ships the
  `pressgang-theme-build` skill — the greenfield build workflow. Next:
  absorb the b-team migration skills plugin.
- **Phase 3 (done)** —
  [Capstan](https://github.com/pressgang-wp/pressgang-capstan) shipped its
  introspection commands (`resolve`, `context --add`, `config dump`,
  `snippets`, `doctor`) and pressgang's shipped fragments now teach agents
  to verify with them instead of inferring from source.
- **Phase 4 (begun)** — local-first docs index: packages ship a
  machine-readable `docs/api-index.json` (Quartermaster already does);
  Bosun surfaces each valid index in the composed guidelines so agents
  read signatures from vendor instead of guessing. Next: standardise the
  generator across packages.
- **Phase 5 (if the tide demands)** — a thin MCP server proxying
  Capstan/WP-CLI.

## 🧪 Testing

```bash
composer install
composer test
```

Shipshape and Bristol fashion. ⚓
