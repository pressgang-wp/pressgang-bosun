## PressGang Snippets (hook library)

Reusable hook-based behaviours live in `pressgang-wp/pressgang-snippets`,
enabled per theme in `config/snippets.php` (class => constructor args).

- Library snippets are namespaced by category: `Theme\`, `Content\`, `Acf\`,
  `Integration\`, `Seo\`, `Google\`, `WooCommerce\`
  (e.g. `'Theme\DisableEmojis' => []`,
  `'Content\RegisterTaxonomyForPostType' => ['taxonomy' => 'category', 'post_type' => 'page']`).
- Before writing a theme snippet, check the library
  (`vendor/pressgang-wp/pressgang-snippets/src/Snippets/`) for a 1:1
  equivalent and prefer it.
- Custom theme snippets implement `PressGang\Snippets\SnippetInterface`,
  register all hooks in the constructor, and do one thing.
