## PressGang 2 (parent theme framework)

This theme is a PressGang 2 child theme: Composer-autoloaded PSR-4 (`src/`),
Timber 2 + Twig rendering, and config-driven bootstrapping.

- Config files in `config/` return arrays only — registration, never logic.
  Each maps to a `PressGang\Configuration\{Studly}` class by filename.
- Controllers are template-scoped view models in `src/Controllers/`:
  side-effect free, no request globals, no writes, no direct rendering.
- Declare a controller's template contract with a context manifest:
  `protected array $context_getters = [ 'news', 'events' ];` — each key is
  populated from its `get_{key}()` getter. Never auto-publish getters.
- Data that reaches Twig should be Timber objects. Convert raw ACF
  relationship/post-object values with
  `PressGang\ACF\TimberMapper::to_timber_posts( $value )`; do not enable
  Timber's global `timber/meta/transform_value` filter.
- Post types and taxonomies with behaviour of their own get a model in
  `src/Models/`, bound in `config/timber-class-map.php`. Before writing a
  class that turns an object into an array for Twig, bind a model instead:
  `Timber::get_post()` / `Timber::get_term()` then return it everywhere,
  including from ACF post-object and taxonomy fields, and it is lazy, so a
  template only pays for what it renders. `name`, `description()`, `link()`
  and `thumbnail()` come free — do not reimplement them.
- `Timber::get_image()` accepts an ACF image array or an attachment ID and
  returns null for anything unusable, so it is the image normaliser. Do not
  hand-roll one — a URL string it cannot resolve is better as null than
  passed through.
- A child `config/*.php` REPLACES the parent theme's file of the same name;
  config merges per file, not per key. Repeat anything the parent declared,
  or it is silently dropped — `timber-class-map.php` and
  `service-providers.php` are the usual casualties.
- Twig compiles an array callable like `[ Foo::class, 'bar' ]` into a literal
  `\Foo::bar()`, baking the class name into the compiled template. Renaming or
  moving such a class does not change the template source, so Timber never
  recompiles: clear the Twig cache on deploy.
- Twig is presentation only: no queries, no request globals, no business
  logic. Escape in Twig (`|e`), sanitise in PHP — never `esc_*` in Twig.
- **Timber ships Twig autoescape off**, so `{{ value }}` prints raw HTML and
  every value a template prints needs escaping by hand: `|e` in text,
  `|e('html_attr')` in attributes, `|e('wp_kses_post')` where the value is
  meant to carry markup. Do not read the rule above as "Twig escapes for
  you". Turning autoescape on globally is not a drop-in fix either — the
  templates that emit markup on purpose would start escaping it.
- Inspect and run the site with WP-CLI (`wp eval`, `wp db query`) and
  `wp capstan` commands where available.
