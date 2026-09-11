---
name: pressgang-simplifier
description: Simplify existing PressGang PHP, controllers, traits, models, configuration and Twig for readability while preserving behaviour. Use for cleanup and refactoring reviews; focus on recently changed code unless a broader scope is requested.
---

# PressGang Simplifier

Make the requested code easier to read and maintain, not merely shorter. Work
within the requested scope; a review-only request produces findings, while a
request to apply improvements authorizes local edits. This skill does not itself
authorize dependency upgrades, upstream changes, commits or publication.

## Establish the contracts

Read the project's composed guidelines and coding standards first. Inspect its
installed package versions, API indexes and relevant source before proposing a
framework replacement. Package-shipped guidance owns API details; do not assume
an example below is supported by every installation.

Review the current diff by default, plus callers needed to understand it. Expand
to whole controllers, traits or the codebase only when requested. Preserve user
changes. Identify the contracts touched: context keys, return shapes, caching,
query results and order, pagination, route status, template selection and hooks.

## Check whether the work is needed

Trace context consumers before polishing producers: inherited templates, block
bodies, includes and embeds, macro call arguments, dynamic context access, and
PHP hooks. Block names are not variable reads; block bodies receive context.
Macros have local scope, but their arguments may use controller context. A text
search alone cannot prove a dynamically accessed key is unused. Remove proven
unused getters and their now-unreachable queries/helpers together.

## Prefer these simplifications

- Use one return when it reads clearly. Remove a cache guard when the following
  `??=` already provides the same lazy evaluation. Keep guards that prevent
  unsafe work or avoid nesting a substantial branch. Do not force one return
  with a result variable, nested ternaries or a larger method.
- Do not add a cache to every getter. A manifest invokes each entry once per
  application; inspect cross-getter calls and repeated rendering before removing
  caches. Retain caching for reused queries or expensive enrichment. A one-use
  getter/resolver pair can usually be one method.
- Choose short names that describe the result. Remove arguments already supplied
  by an existing object when every caller intends the same meaning. Preserve
  public APIs and template/getter conventions when renaming.
- Order trait uses and properties before methods; keep related getters together
  and private implementation helpers afterwards. Use one context-manifest entry
  and one fluent method call per line. Follow project rules for arrays and PHPDoc.
- Keep controllers focused on view composition. Move cohesive, reusable domain
  queries or behaviour to traits when this makes their callers clearer. A small
  method that loads, selects and combines one result can still have one
  responsibility. A shared trait may expose the convention getter directly when
  multiple controllers need the same context key and query semantics. Keep a
  one-off view query in its controller; do not create a single-use trait merely
  to shorten the class. Avoid helpers that merely rename a line or pass arguments
  on. Check composed traits for method and property collisions.
- Replace constructor-only controllers with parent-controller/template config
  only when the installed parent supplies equivalent behaviour. Check context,
  hooks, template precedence and 404 handling, not just the template filename.
- Check inherited config and the snippets library before writing custom hooks.
  Inspect config merge/replacement semantics before removing explicit entries.
  A parent default is useful only when it expresses the intended behaviour.
- Keep PHPDoc useful: return shapes, input meanings and non-obvious constraints.
  Remove session history and comments that paraphrase a statement. Retain parent
  configuration documentation where that is the project's convention.

## Quartermaster and Timber

Use fluent Quartermaster methods for supported query operations. Keep queries
visually linear; name intermediate values when nested preparation obscures the
query. Traits can return builders when callers refine the query. Shared selection or
enrichment helpers may return finished results when that is their useful contract. Do not
replace consumption of WordPress's existing main query with a second query.

Use standard query-var bindings when they state intent more clearly than manual
reads. Do not replace a readable local variable with a custom callback containing
sanitization, decoding and query logic. Use named methods for meaningful domain
bindings, and report reusable API gaps instead of hiding them in `tapArgs()`.
Do not add an upstream feature merely to eliminate one local expression.

Let the documented API own input sanitization. Check null, absent, empty and
malformed input separately; do not assume they are interchangeable. Never add
URL decoding to already-decoded query variables. Keep output escaping in Twig.

Preserve query semantics when replacing raw arguments: empty constraints, root
versus nested OR, relationship ID representation, metadata types, ordering
filters, pagination and missing metadata can change results. Preserve the
terminal's shape: an empty collection object and an empty array behave
differently in Twig. `all()` and a negative limit need not produce identical args.
WordPress `hide_empty` uses a taxonomy term's aggregate count; it does not prove
that the term has results for a listing's particular post type. Do not use it as
a post-type-specific filter-validity check.

Read presentation-only fields from the existing model in Twig with `post.meta()`
or `term.meta()`; use a local Twig variable for repeated expressions. Do not
create controller getters just to forward fields. Keep queries, selection rules,
relationship normalization and enrichment in PHP. Preserve output escaping and
field ownership; an ambient `get_field()` read is not automatically equivalent.

For custom listings, pass `items.pagination()` explicitly to the pagination
partial when that removes controller plumbing. Use the displayed collection,
never a second query. Retain inherited archive pagination where it already fits.
Timber PostQuery caches its pagination object; do not duplicate that cache.
Remove unused pagination for unpaged listings. Keep collection caches needed by
other PHP getters, even when pagination moves to Twig.

Use Timber factories and registered class maps to construct models; PHP casts
cannot convert WordPress objects to Timber models. Keep normalization helpers
when they preserve empty values, remove missing objects or produce the required
array shape. Check how native conversion handles an empty input before replacing
such a helper. Use models/dynamic getters for cohesive post-specific behaviour
when supported by the project, not as a blanket relocation of controller code.

Read [examples](references/examples.md) when considering cache guards,
constructor-only controllers, search bindings or relationship conversion.

## Apply and verify

Make small, cohesive edits. Preserve behaviour by default. If a supposed cleanup
reveals a bug, distinguish the correction from the refactor and follow the user's
existing scope and approval requirements; do not silently change semantics.

Run the project's appropriate checks (for example its existing `composer check`,
PHPCS, PHPStan or tests). Do not introduce a new toolchain for a small cleanup.
Choose additional verification based on what changed:

- Renames and organization: check callers, framework conventions and syntax.
- Query construction: compare arguments and, where results could differ, ordered
  IDs, totals and pagination. Include empty and missing inputs.
- Context or routing: check representative rendered pages, HTTP status and form
  state, including selected/checked values. Preserve baseline evidence.
- Caching and conversion: verify lazy execution and the established empty-result
  shape. Do not call a change equivalent solely because lint passes.

Stop once the requested scope is clearer and relevant checks pass. Report the
significant changes, useful abstractions retained, checks performed and any
unverified behaviour. Do not claim a performance benefit without measurement.
