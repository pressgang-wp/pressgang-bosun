# ADR 0001: An MCP server is the missing layer, owned by Capstan and wired by Bosun

- Status: Accepted — largely implemented (2026-07-16)
- Date: 2026-07-16

## Context

Bosun exists to give AI agents the context to use PressGang at its best, modelled
openly on the Laravel-ecosystem tooling. Benchmarking the ecosystem against that
tooling — [Laravel Boost](https://laravel.com/ai/boost),
[laravel/agent-skills](https://github.com/laravel/agent-skills), and
[Filament Blueprint](https://filamentphp.com/docs/5.x/introduction/ai) — the
modern framework-AI stack has **four layers**:

1. **Guidelines** — version/package-aware rules composed into the project.
2. **Skills** — packaged `SKILL.md` task workflows.
3. **Introspection + docs** — live tools that answer "what does *this* app look
   like?" and "what does the API actually say, at *this* version?".
4. **A transport that exposes 2–3 to any editor** — for Boost, an MCP server.

PressGang already holds most of this, and holds some of it better than Laravel:

- **Guidelines** — Bosun composes them in three tiers (package-shipped →
  built-in → theme-local `.ai/`), **gated on config opt-ins**, into both
  `CLAUDE.md` and `AGENTS.md` with a lock-ref inventory header. This is finer
  than Boost's package-presence gating.
- **Skills** — Bosun's `SkillInstaller` **auto-installs feature-gated skills** to
  `.claude/skills/`, where Laravel ships a separate manual marketplace.
- **Introspection** — [Capstan](https://github.com/pressgang-wp/pressgang-capstan)
  already exposes `resolve`, `context`, `config dump`, `snippets`, `matrix`,
  `doctor`, `about` — the functional equivalent of Boost's route/config/app-info
  tools.
- **Local docs** — packages ship `docs/api-index.json`; Bosun points agents at it.
- **Runtime signals** — Shakedown's `observer.php` already captures per-request
  template/controller resolution and PHP issues as headers.

The gap is **not capability** — it is **transport**. Every piece above is reachable
only by an agent that can shell out to `wp capstan …` (in practice, Claude Code
with Bash). Consequences:

- Editor agents without a shell — Cursor, Windsurf, JetBrains AI — get the static
  guidelines and nothing live. Boost reaches all of them over MCP.
- "Docs search" is "open this JSON file", not a queryable, version-matched
  endpoint — the single most-praised Boost feature.
- The runtime signals Shakedown captures are invisible during ordinary
  development; there is no logs/errors tool.

Bosun's own roadmap already names this as Phase 5, "if the tide demands". The
finding of this ADR is that the tide demands it *now*: it is the highest-leverage
item left, and everything it needs already exists. It is effectively a **wiring
job over shipped components**, not new capability.

## Decision

Adopt the MCP server as a first-class ecosystem layer, with a clean ownership
split that preserves each package's role:

- **Capstan owns the server.** It already owns theme introspection; it gains a
  `wp capstan mcp serve` stdio MCP server that exposes that introspection, plus a
  docs search over the api-indexes and a logs tool over the observer signals.
  Nothing in the server is new capability — each tool proxies an existing command
  or data source.
- **Bosun wires it.** `wp bosun install` writes the MCP registration into the
  correct per-agent config file (the same `AgentTargets` mechanism that already
  targets `CLAUDE.md`/`AGENTS.md`), gated on Capstan being installed. Bosun never
  implements a tool; it pipes the order.
- **Read-first.** Every default tool is read-only. Anything that writes (eval,
  scaffolding) is opt-in and preview-first, honouring Capstan's dry-run-by-default
  philosophy.

This also resolves a secondary finding: **skills should target more than Claude.**
`SkillInstaller` is extended to install to each configured agent target (e.g.
`.cursor/`) alongside `.claude/skills/`, matching Laravel's multi-agent skills.

### The Capstan MCP tool surface

Every tool is a thin proxy over something Capstan (or a sibling package) already
produces. Read-only unless noted.

| MCP tool | Backed by | Boost analogue |
|---|---|---|
| `pressgang_about` | `wp capstan about` | Application Info |
| `pressgang_doctor` | `wp capstan doctor` | Application Info / health |
| `pressgang_config` `(key?)` | `wp capstan config dump` | Configuration Access |
| `pressgang_resolve` `(url)` | `wp capstan resolve` | Route Inspector |
| `pressgang_matrix` | `wp capstan matrix --resolve` | Route Inspector |
| `pressgang_context` `(controller)` | `wp capstan context` | — (controller manifest) |
| `pressgang_snippets` | `wp capstan snippets` | — |
| `pressgang_docs_search` `(query, package?, version?)` | the `docs/api-index.json` corpus, version-matched from `composer.lock` | **Documentation Search** |
| `pressgang_logs` `(url?, limit?)` | `observer.php` template/controller + PHP-issue capture | Error Tracking / Browser Logs |
| `pressgang_sounding` `(php, write?)` — **gated** | `wp eval`, wrapped for structured returns + rollback (see [below](#deferred-design-pressgang_sounding-a-structured-probe-not-a-bare-eval)) | Tinker |
| `pressgang_make` `(kind, args)` — **write, gated, preview-first** | `wp capstan make …` | Artisan Commands (list/run) |

`pressgang_docs_search` is the tool that turns PressGang's static local-first
indexes into Boost's headline capability: it reads signatures and WordPress-doc
links straight from the vendor `api-index.json` the project actually has installed,
so answers are correct for *this* theme's versions with no network call.

### The registration Bosun writes

During `wp bosun install`, when Capstan is present, Bosun writes/merges an MCP
entry into each agent target's config — owning only its marked keys, never the
whole file, exactly as it does for the guideline region. For the Claude target,
`.mcp.json` in the theme root:

```json
{
  "mcpServers": {
    "pressgang": {
      "command": "wp",
      "args": ["capstan", "mcp", "serve", "--path=."],
      "env": {}
    }
  }
}
```

For a Cursor target, the same server object under `.cursor/mcp.json`. `--path`
pins the WordPress root the server boots against (the server is a WP-CLI command
and must bootstrap WordPress); it defaults to the invocation directory. The server
is a **development tool** — it is never registered in a production context, and
the write tools stay gated behind explicit opt-in.

## Consequences

- **Parity-and-beyond with Boost.** PressGang gains the one layer it lacked, over
  components already built. `pressgang_docs_search` + `pressgang_logs` close the
  two features Capstan's CLI could not express (searchable version-aware docs; live
  runtime signals).
- **Every editor, not just Claude Code.** Introspection stops being Bash-gated;
  Cursor/Windsurf/JetBrains agents get the live tools.
- **Ownership stays clean.** Capstan introspects, Bosun briefs and wires, Shakedown
  tests, Muster seeds. No package grows a second job.
- **Roadmap reorders.** Phase 5 is promoted; the realistic sequence becomes: finish
  skills consolidation and multi-agent skill targeting (Phase 2), then ship
  `wp capstan mcp serve` + Bosun registration (this ADR), then standardise the
  api-index generator so `pressgang_docs_search` covers every package (Phase 4).
- **New surface to maintain.** An MCP server is a support surface (protocol
  versions, editor quirks). Mitigated by keeping every tool a thin proxy with no
  logic of its own, and by shipping read-only tools first.

## Implementation status

Shipped:

- `wp capstan mcp serve` — the stdio JSON-RPC server, with read tools proxying
  `resolve`, `matrix`, `doctor`, `config`, `snippets`, `context`, `about`
  (`context`/`about` gained `--format=json`).
- `pressgang_docs_search` over the api-index corpus, version-matched from vendor.
- `pressgang_logs` over the WordPress debug log (the observer-backed source
  remains a later enhancement).
- `pressgang_make` — bounded, preview-first scaffolding, gated behind
  `--allow-write`.
- Bosun writes the registration (`.mcp.json`, `.cursor/mcp.json`) on `install`,
  gated on a Capstan that can serve MCP.
- One standard api-index generator (`wp capstan make api-index`); Quartermaster
  and Muster adopted it.

Deferred:

- `pressgang_sounding` (a structured PHP probe superseding a bare
  `pressgang_eval`) — an RCE surface; specced in the next section, awaits
  explicit owner sign-off before it is built or registered.
- The observer-backed `pressgang_logs` source (Shakedown signals).
- Multi-agent skill targeting (Bosun Phase 2).

## Deferred design: `pressgang_sounding` (a structured probe, not a bare eval)

**Status: proposed, not built.** Needs explicit owner sign-off (it is an RCE
surface) before implementation.

### Why not just wrap `wp eval`

WordPress already has a Tinker: `wp shell` (interactively, PsySH-backed) and
`wp eval` (one-shot) run PHP with WordPress loaded. A tool that merely shells to
`wp eval` adds almost nothing — worse, in a Bash-capable client (Claude Code) the
agent can already call `wp eval` directly, so the wrapper is *redundant* and
contributes only RCE surface. A bare `pressgang_eval` is therefore **rejected**.

The tool earns its place only by doing what `wp eval` cannot. Hence a *sounding* —
in seamanship, dropping a lead line to probe what lies below; "to sound out" is to
investigate. The name frames the tool as **observation, not execution**, and the
design follows that framing.

### What it adds over `wp eval`

1. **Returns the value, not just stdout.** `wp eval` shows only what you `echo`.
   A sounding captures the expression's **return value** and renders it with a
   typed dumper (PsySH / Symfony VarDumper), so
   `Quartermaster::posts('event')->toArray()` comes back as structured data with
   no hand-written `var_export`.
2. **Captures exceptions and notices.** Instead of a fatal or a raw
   `Trying to access array offset on null` landing mid-output, the run is trapped
   and returned as structured fields the agent can reason about.
3. **PressGang-aware context + a safe-by-default mode.** Pre-wire the idioms
   (Timber, Quartermaster, resolve a controller's live context by name) so
   soundings are terse; and — the one guarantee `wp eval` cannot give — wrap the
   call in a `$wpdb` `START TRANSACTION` / `ROLLBACK` so the **default read mode
   cannot mutate the database**. Only `write: true` (itself under `--allow-write`)
   commits.

### Shape

```jsonc
// tools/call pressgang_sounding
{
  "php":   "return Quartermaster::posts('event')->status('publish')->toArray();",
  "write": false                    // default; true wraps a commit instead of rollback
}
// →
{
  "result":    "<typed dump of the return value>",
  "stdout":    "<anything echoed>",
  "notices":   ["Undefined array key \"x\" in …"],   // captured, not inline
  "exception": null                                   // or { class, message, file, line }
}
```

### Safety posture

- **Two gates, not one.** The tool is registered only under `--allow-write`
  (server-launch gate); *mutating the database* additionally requires
  `write: true` per call. Read soundings roll back unconditionally.
- **Production-guarded.** Inherits `mcp serve`'s refusal to start in a production
  environment; a sounding never runs against production.
- **Honest about limits.** The `$wpdb` transaction guards the database only —
  filesystem writes, external HTTP, and mail are *not* rolled back. The tool
  description must say so; "read mode" means "DB-safe", not "side-effect-free".
- **Ownership.** Lives in Capstan alongside the other tools; Bosun still only
  registers the server, never implements the tool.

### Open questions

- Whether to depend on PsySH/VarDumper for dumping, or ship a minimal formatter.
- Whether transaction-wrapping is reliable across every storage engine a theme
  might run on (MyISAM ignores transactions — detect and downgrade to a loud
  warning rather than a false safety promise).

## Not chosen

- **A bespoke non-MCP protocol.** MCP is what the target editors already speak;
  inventing transport would forfeit the "any editor" win.
- **Bosun implementing the tools.** That would collapse the introspection/briefing
  boundary; Bosun stays the layer that *pipes* orders, not the one that answers.
- **A Blueprint-style planning product now.** Filament Blueprint's spec-before-build
  is attractive, but the `pressgang-theme-build` skill already covers most of it;
  revisit a `capstan make plan` spec-emitter only after the MCP layer lands.
