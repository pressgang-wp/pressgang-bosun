# Simplification examples

These illustrate decisions, not mandatory rewrites. Follow the installed APIs
and project conventions. Names and domain values are illustrative.

## Remove a redundant cache guard

Before:

```php
protected function get_articles(): mixed {
    if ( null !== $this->articles ) {
        return $this->articles;
    }

    return $this->articles ??= $this->article_query()
        ->timber();
}
```

After:

```php
protected function get_articles(): mixed {
    return $this->articles ??= $this->article_query()
        ->timber();
}
```

The right-hand side remains lazy. Do not remove a guard when intervening work
must also be skipped. Nullable results still are not cached as a distinct
"resolved but absent" state; adding that state would be a separate decision.

## Use config for a template-only controller

A class whose only change is passing a Twig path to `PageController` can use a
mapping when the installed PressGang supports it:

```php
'research-subpage' => [
    'controller' => \PressGang\Controllers\PageController::class,
    'template'   => 'page/research-subpage.twig',
],
```

Update callers and remove the redundant class only after checking the same
context and template are produced. Class-specific render hooks may change. A
404 needs a suitable not-found controller, not a page controller that requires a
current post. Never map an abstract class just because its constructor matches.

## Prefer a standard search binding to a custom callback

With a Quartermaster version supporting explicit binding defaults:

```php
->bindQueryVars( function ( Binder $bindings ): void {
    $bindings->relevanssi( 'project-search', allowEmpty: true, default: '' );
} )
```

Or within an existing map:

```php
'project-search' => Bind::relevanssi( 'project-search', allowEmpty: true, default: '' ),
```

Use the corresponding `PressGang\Quartermaster\Bindings` import. Here the empty
default is a deliberate requirement, not a recommendation for every search.
Missing/null input uses that default; malformed non-scalar input is skipped.
The builder owns sanitization. Verify the installed contract instead of copying
these options into an older release.

If special input preparation is necessary, a named local followed by a direct
fluent call can be clearer. Avoid a generic transformation framework for one
field. Removing legacy double decoding is a behaviour correction: a literal
`C++` must not silently become spaces, and that deserves an explicit check.

## Keep useful conversion and selection helpers

```php
$featured = TimberMapper::to_timber_posts( $term->meta( 'featured_projects' ) );
```

This can be clearer than a native bulk conversion plus empty guards and array
normalization. Inspect the mapper and factory before replacing it. In some
Timber versions `get_posts([])` means the current query, not an empty selection.
Native factories should still choose the mapped post classes.

A method that keeps editorial selections and adds fallback items has one
cohesive responsibility. Name the remaining count, avoid querying when no slots
remain, and preserve editorial order and duplicates policy. A target of three
may mean "fill to three when possible", not "truncate to three". Do not extract
separate helpers for counting and merging merely to claim SRP.

## Keep a useful guard

```php
if ( ! $selected_ids ) {
    return [];
}

return $this->selected_articles( $selected_ids )
    ->toArray();
```

Keep this if an empty selection would otherwise remove the constraint and fetch
unrelated posts. One return is a readability preference, not a reason to alter
empty-query behaviour or bury a multi-step query inside a ternary.

## Let a shared trait own the getter

When several controllers expose the same context key with the same query, the
trait can provide the convention getter directly:

```php
trait HasResearchThemes {
    protected function get_research_themes(): array {
        return $this->taxonomy_terms( 'research-theme', true );
    }
}
```

Do not consolidate getters whose query flags differ. `hide_empty: true` and
`hide_empty: false` are different contracts, even when the current database
happens to produce the same terms. WordPress also calculates `hide_empty` across
the taxonomy's registered object types, so it cannot guarantee that a term has
results for one listing post type.

Keep a substantive getter in its controller when it has one consumer. Moving it
to a single-use trait shortens the controller without creating useful reuse and
makes the view's defining query harder to find.

## Presentation fields and pagination

A getter that only returns `$this->get_post()->meta( 'intro_title' )` can become
`{{ post.meta('intro_title')|e }}` in its consuming view. Preserve the existing
escaping contract for rich text. Repeated values can use a local Twig variable.
Check inherited blocks, includes and macro arguments before deleting the alias.

A custom page listing can pass pagination from the collection already displayed:

```twig
{% include 'partials/modules/pagination.twig' with {
    pagination: news_items.pagination()
} %}
```

The partial still receives `pagination`; the controller no longer needs a getter
only to expose it. Remove the query cache only if no other PHP getter or execution
path needs the same result. Ordinary PostsController archives can keep inherited
pagination. Relationship mapping and query construction remain in PHP.
