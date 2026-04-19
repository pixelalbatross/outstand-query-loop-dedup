# Post Deduplication

When you use multiple Query Loop blocks on the same page, they can display the same posts. This feature adds an opt-in mechanism that prevents duplicates across Query Loop blocks (and any custom blocks you opt in), ensuring each post appears only once.

## Use cases

- Featured posts section followed by a latest posts feed.
- Landing pages with multiple curated query loops.
- Inherited queries alongside custom queries on archive pages.
- Manual content pickers (e.g. an "article card" block) that should reserve their post against later automated lists.

## How it works

1. Add multiple Query Loop blocks to a page or template.
2. Select a Query Loop block and open the block settings sidebar.
3. Enable **Exclude duplicate posts** under the **Deduplication** panel.
4. Posts shown by earlier Query Loop blocks are excluded from this block's query.

### Auto-enabled tracking

Tracking is enabled for the **whole page** as soon as one block has `excludeDuplicates` set to `true`. You don't need to flag every block — earlier blocks contribute their post IDs automatically once any later block opts in.

Detection runs in three places:

- `template_include` (priority 1) — pre-walks the composed FSE template, recursively resolving `core/template-part` references so a flag in any part activates tracking before any block renders.
- `the_content` (priority 8) — scans post content before `do_blocks` runs.
- `pre_render_block` (priority 5) — fallback per-query subtree scan.

### Tracking flow

```
Page Render
  |
  v
[Detect: any block has excludeDuplicates? => tracking_enabled = true]
  |
  v
[Query Loop 1] (no flag)
  |- Renders posts A, B, C
  |- Tracks IDs: A, B, C  (because tracking is enabled page-wide)
  |
  v
[Query Loop 2] (excludeDuplicates: true)
  |- Excludes A, B, C via post__not_in
  |- Renders posts D, E, F
  |- Tracks IDs: D, E, F
  |
  v
[Inherited Query] (excludeDuplicates: true)
  |- Filters $wp_query->posts to remove A, B, C, D, E, F
  |- Renders remaining posts
  |- Restores $wp_query->posts
```

### Supported query types

- **Custom queries** (non-inherited): tracked IDs are merged into `post__not_in` via `query_loop_block_query_vars`. IDs returned by the block's `WP_Query` are captured via a one-shot `the_posts` filter.
- **Inherited queries**: `$wp_query->posts` is filtered before rendering and restored afterward.
- **Custom blocks**: any block whose post ID(s) you can resolve from its parsed attributes. See [Custom block resolvers](#custom-block-resolvers).

## PHP API

### `exclude_duplicates` query arg

Use in any `WP_Query` to exclude posts already shown on this request:

```php
$query = new WP_Query( [
    'exclude_duplicates' => true,
    'posts_per_page'     => 10,
] );
```

This merges all tracked post IDs into `post__not_in`.

### `Deduplicator` class

Direct access to the request-scoped registry:

```php
use Outstand\WP\QueryLoop\Dedup\Deduplicator;

Deduplicator::add( 123, 456 );      // Push IDs into the pool.
Deduplicator::has( 123 );            // bool — already tracked?
Deduplicator::get();                 // int[] — all tracked IDs.
Deduplicator::count();               // int   — number tracked.
Deduplicator::clear();               // void  — wipe (test/reset).
```

The registry is per-request, in-memory, static. No persistence.

## Filters

### `outstand_query_loop_deduplication_resolvers`

Register custom blocks so their rendered post IDs feed into the deduplication pool. Each entry maps a block name to a callable:

```php
callable( array $parsed_block, WP_Block $block ): int|int[]
```

The callable must return a single int post ID, an array of int IDs, or `0`/`[]` to skip.

Example — register two custom blocks:

```php
add_filter( 'outstand_query_loop_deduplication_resolvers', function ( array $resolvers ): array {

    // A picker block that stores a single post ID.
    $resolvers['acme/article-card'] = static function ( array $parsed_block ): int {
        return (int) ( $parsed_block['attrs']['postId'] ?? 0 );
    };

    // A grid block that stores multiple posts as an array of objects.
    $resolvers['acme/featured-grid'] = static function ( array $parsed_block ): array {
        $posts = $parsed_block['attrs']['posts'] ?? [];
        return array_map( 'intval', wp_list_pluck( $posts, 'id' ) );
    };

    return $resolvers;
} );
```

The plugin hooks `render_block_{name}` for each resolver on `init`. Each invocation is gated on `should_track` — so IDs only flow into the pool when page-wide tracking is active (or when `outstand_query_loop_deduplication_should_track` forces it on).

### `outstand_query_loop_deduplication_should_track`

Override whether a specific block's rendered posts should be tracked.

Parameters:

- `bool     $should_track` — default decision (true when tracking is enabled page-wide).
- `string   $query_id`     — the block's `queryId` from context.
- `array    $attributes`   — the block's attributes.
- `WP_Block $block`        — the block instance.

```php
add_filter( 'outstand_query_loop_deduplication_should_track', function ( bool $should_track, string $query_id, array $attrs, WP_Block $block ): bool {
    // Never count the hero block towards dedup.
    if ( ( $attrs['className'] ?? '' ) === 'is-style-hero' ) {
        return false;
    }
    return $should_track;
}, 10, 4 );
```

### `outstand_query_loop_deduplication_should_exclude`

Override whether a specific block should exclude already-tracked posts from its query.

Parameters:

- `bool     $should_exclude` — default from `excludeDuplicates` attribute or context.
- `string   $query_id`       — the block's `queryId`.
- `array    $attributes`     — the block's attributes.
- `WP_Block $block`          — the block instance.

```php
add_filter( 'outstand_query_loop_deduplication_should_exclude', function ( bool $should_exclude, string $query_id, array $attrs, WP_Block $block ): bool {
    // Always exclude duplicates inside the related-posts widget.
    if ( ( $attrs['className'] ?? '' ) === 'related-posts' ) {
        return true;
    }
    return $should_exclude;
}, 10, 4 );
```

## JavaScript / Editor

The block editor preview also dedupes. A higher-order component wraps `core/post-template` and, when the parent `core/query` has `excludeDuplicates: true`, passes a modified `query` context (with preceding queries' resolved IDs merged into `exclude`) down to the child `BlockEdit`. No attributes are persisted to the saved post content.

### Frontend parity

The editor mirrors frontend semantics. Critically:

- An **unflagged** query fetches records exactly as its parent `query` defines — it does **not** inherit preceding queries' exclusions. If it did, its resolved IDs would diverge from the frontend `WP_Query` (which also doesn't exclude), corrupting the union consumed by later flagged queries.
- A **flagged** query fetches records with preceding resolved IDs merged into `exclude`.
- Every rendered `core/post-template` publishes its resolved IDs to the dedup store, regardless of the flag — so later flagged queries see the same pool the frontend would.

### Extending

To extend the preview to custom block attribute shapes, you need a separate JS HOC — there is no JS resolver filter yet. PHP resolvers cover the frontend; the editor preview only knows how to resolve `core/query` siblings out of the box.

## Behaviour matrix

| Scenario                                                            | Track | Exclude |
|---------------------------------------------------------------------|-------|---------|
| No block on page has `excludeDuplicates`                            | No    | No      |
| Earlier block has no flag, later block has `excludeDuplicates=true` | Yes   | Yes (later only) |
| Block has `excludeDuplicates=true`                                  | Yes   | Yes     |
| Tracking enabled + `should_track` returns false                     | No    | Depends on the block's own `excludeDuplicates` |
| `excludeDuplicates=true` + `should_exclude` returns false           | Yes   | No      |
| Custom block registered via resolvers, tracking enabled             | Yes   | n/a     |
| Inherited query (`query.inherit=true`) + `excludeDuplicates=true`   | n/a (post-template loop) | Yes — via `$wp_query->posts` filter |

## Known limitations

- **Editor preview for custom blocks** — the JS HOC only resolves `core/query` siblings. Custom block resolvers are PHP-only.
- **Editor preview taxonomy queries** — `taxQuery` is dropped from REST args; the preview doesn't mirror taxonomy filters when computing dedup. Frontend is unaffected.
- **Stale order across pagination** — tracked IDs are per-request only. AJAX/REST follow-up requests start with an empty pool.
- **Resolver registration timing** — custom-block resolvers must be registered via the `outstand_query_loop_deduplication_resolvers` filter before `init` priority 10. Register on `plugins_loaded` or earlier.
