<?php

namespace Outstand\WP\QueryLoop\Dedup;

use WP_Block;
use WP_Query;

/**
 * Main deduplication class.
 *
 * Tracks rendered post IDs and excludes them from later Query Loop blocks
 * on the same page. Custom blocks can contribute IDs by registering a
 * resolver via the `outstand_query_loop_deduplication_resolvers` filter.
 */
class QueryDeduplication extends BaseModule {

	/**
	 * Sentinel query arg used to identify a post-template's WP_Query in `the_posts`.
	 *
	 * @var string
	 */
	private const CAPTURE_FLAG = 'outstand_dedup_capture';

	/**
	 * Whether any block on the current page opts into deduplication.
	 *
	 * @var bool
	 */
	private bool $tracking_enabled = false;

	/**
	 * Stack of global query backups for inherited query blocks.
	 *
	 * @var array<int, array{query_id: string, posts: array, post_count: int, found_posts: int, max_num_pages: int, current_post: int}>
	 */
	private array $global_query_backup = [];

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'load_resolvers' ] );
		add_action( 'pre_get_posts', [ $this, 'handle_exclude_duplicates_query_arg' ] );
		add_filter( 'template_include', [ $this, 'prewalk_template' ], 1 );
		add_filter( 'the_content', [ $this, 'detect_tracking' ], 8 );
		add_filter( 'pre_render_block', [ $this, 'detect_tracking_in_template' ], 5, 2 );
		add_filter( 'pre_render_block', [ $this, 'maybe_filter_inherited_query' ], 10, 3 );
		add_filter( 'query_loop_block_query_vars', [ $this, 'setup_query_tracking' ], 10, 2 );
		add_filter( 'query_loop_block_query_vars', [ $this, 'maybe_exclude_tracked_posts' ], 11, 2 );
		add_filter( 'render_block_core/post-template', [ $this, 'maybe_restore_inherited_query' ], 10, 3 );
	}

	/**
	 * Register custom block resolvers and bind their render hooks.
	 *
	 * Callers must register their resolvers before `init` priority 10. Loading
	 * the filter on `plugins_loaded` or earlier `init` (priority < 10) is safe.
	 *
	 * @return void
	 */
	public function load_resolvers(): void {

		/**
		 * Filters the map of block name => post-ID resolver.
		 *
		 * Each callable receives the parsed block array and WP_Block instance
		 * and must return an int post ID or an array of int IDs.
		 *
		 * @param array<string, callable> $resolvers Block name to resolver map.
		 */
		$resolvers = apply_filters( 'outstand_query_loop_deduplication_resolvers', [] );

		if ( ! is_array( $resolvers ) ) {
			return;
		}

		foreach ( $resolvers as $block_name => $callable ) {
			if ( ! is_string( $block_name ) || ! is_callable( $callable ) ) {
				continue;
			}

			add_filter(
				"render_block_{$block_name}",
				function ( $content, $parsed_block, $block ) use ( $callable ) {
					return $this->track_resolved_post( $content, $parsed_block, $block, $callable );
				},
				10,
				3
			);
		}
	}

	/**
	 * Pre-walk the composed block template (FSE) before any block renders.
	 *
	 * Resolves template-part references recursively so a dedup-enabled query
	 * in one part is detected even when an earlier non-flagged query lives in
	 * another part. Runs at `template_include` priority 1 — before WP renders
	 * the block template through `wp-includes/template-canvas.php`.
	 *
	 * @param string $template Template path.
	 *
	 * @return string Unchanged template path.
	 */
	public function prewalk_template( string $template ): string {
		global $_wp_current_template_content;

		if ( $this->tracking_enabled ) {
			return $template;
		}

		if ( empty( $_wp_current_template_content ) ) {
			return $template;
		}

		if ( false === strpos( $_wp_current_template_content, '"excludeDuplicates":true' )
			&& false === strpos( $_wp_current_template_content, 'wp:template-part' )
		) {
			return $template;
		}

		foreach ( parse_blocks( $_wp_current_template_content ) as $block ) {
			if ( $this->has_dedup_block( $block ) ) {
				$this->tracking_enabled = true;
				break;
			}
		}

		return $template;
	}

	/**
	 * Enable tracking if the post content contains a dedup-enabled query block.
	 *
	 * @param string $content Post content.
	 *
	 * @return string Unchanged content.
	 */
	public function detect_tracking( string $content ): string {

		if ( $this->tracking_enabled ) {
			return $content;
		}

		if ( false === strpos( $content, '"excludeDuplicates":true' ) ) {
			return $content;
		}

		foreach ( parse_blocks( $content ) as $block ) {
			if ( $this->has_dedup_block( $block ) ) {
				$this->tracking_enabled = true;
				break;
			}
		}

		return $content;
	}

	/**
	 * Enable tracking when rendering a template tree that contains a dedup block.
	 *
	 * @param string|null $pre_render   Pre-rendered content.
	 * @param array       $parsed_block Parsed block.
	 *
	 * @return string|null Unchanged.
	 */
	public function detect_tracking_in_template( $pre_render, array $parsed_block ) {

		if ( $this->tracking_enabled ) {
			return $pre_render;
		}

		if ( 'core/query' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $pre_render;
		}

		if ( $this->has_dedup_block( $parsed_block ) ) {
			$this->tracking_enabled = true;
		}

		return $pre_render;
	}

	/**
	 * Filter the global query for inherited query blocks before rendering.
	 *
	 * @param string|null   $pre_render   Pre-rendered content.
	 * @param array         $parsed_block Parsed block data.
	 * @param WP_Block|null $parent_block Parent block instance.
	 *
	 * @return string|null Unchanged.
	 */
	public function maybe_filter_inherited_query( $pre_render, array $parsed_block, ?WP_Block $parent_block ) {
		global $wp_query;

		if ( 'core/post-template' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $pre_render;
		}

		if ( ! $parent_block instanceof WP_Block ) {
			return $pre_render;
		}

		$inherit = $parent_block->attributes['query']['inherit'] ?? false;

		if ( ! $inherit || ! $this->should_exclude( $parent_block ) ) {
			return $pre_render;
		}

		if ( Deduplicator::count() === 0 ) {
			return $pre_render;
		}

		$this->global_query_backup[] = [
			'query_id'      => (string) ( $parent_block->attributes['queryId'] ?? '' ),
			'posts'         => $wp_query->posts,
			'post_count'    => $wp_query->post_count,
			'found_posts'   => $wp_query->found_posts,
			'max_num_pages' => $wp_query->max_num_pages,
			'current_post'  => $wp_query->current_post,
		];

		$tracked         = Deduplicator::get();
		$original_count  = (int) $wp_query->post_count;
		$wp_query->posts = array_values(
			array_filter(
				$wp_query->posts,
				static fn( $post ) => ! in_array( (int) $post->ID, $tracked, true )
			)
		);

		$filtered_count       = count( $wp_query->posts );
		$removed              = max( 0, $original_count - $filtered_count );
		$wp_query->post_count = $filtered_count;
		$wp_query->found_posts = max( 0, (int) $wp_query->found_posts - $removed );

		$per_page = (int) $wp_query->get( 'posts_per_page' );
		if ( $per_page <= 0 ) {
			$per_page = (int) get_option( 'posts_per_page', 10 );
		}
		$wp_query->max_num_pages = $per_page > 0
			? (int) ceil( $wp_query->found_posts / $per_page )
			: 0;

		return $pre_render;
	}

	/**
	 * Wire per-query tracking by tagging the query args with a capture sentinel.
	 *
	 * The sentinel lets `capture_query_posts` identify the right WP_Query and
	 * ignore unrelated queries that may run in between.
	 *
	 * @param array    $query Query arguments.
	 * @param WP_Block $block The post-template block instance.
	 *
	 * @return array Modified query arguments.
	 */
	public function setup_query_tracking( array $query, WP_Block $block ): array {

		if ( ! $this->should_track( $block ) ) {
			return $query;
		}

		$query[ self::CAPTURE_FLAG ] = true;

		add_filter( 'the_posts', [ $this, 'capture_query_posts' ], 10, 2 );

		return $query;
	}

	/**
	 * Add tracked post IDs to the block's `post__not_in` query arg.
	 *
	 * @param array    $query Query arguments.
	 * @param WP_Block $block The post-template block instance.
	 *
	 * @return array Modified query arguments.
	 */
	public function maybe_exclude_tracked_posts( array $query, WP_Block $block ): array {

		if ( ! $this->should_exclude( $block ) ) {
			return $query;
		}

		$tracked = Deduplicator::get();

		if ( empty( $tracked ) ) {
			return $query;
		}

		$existing              = $query['post__not_in'] ?? [];
		$query['post__not_in'] = array_values( array_unique( array_merge( $existing, $tracked ) ) );

		return $query;
	}

	/**
	 * Restore the global query after rendering an inherited query block.
	 *
	 * @param string   $block_content Block content.
	 * @param array    $parsed_block  Parsed block data.
	 * @param WP_Block $block         Block instance.
	 *
	 * @return string Unchanged content.
	 */
	public function maybe_restore_inherited_query( string $block_content, array $parsed_block, WP_Block $block ): string {
		global $wp_query;

		if ( empty( $this->global_query_backup ) ) {
			return $block_content;
		}

		$query_id = (string) ( $block->context['queryId'] ?? '' );
		$top      = end( $this->global_query_backup );

		if ( $top['query_id'] !== $query_id ) {
			return $block_content;
		}

		$backup                  = array_pop( $this->global_query_backup );
		$wp_query->posts         = $backup['posts'];
		$wp_query->post_count    = $backup['post_count'];
		$wp_query->found_posts   = $backup['found_posts'];
		$wp_query->max_num_pages = $backup['max_num_pages'];
		$wp_query->current_post  = $backup['current_post'];

		return $block_content;
	}

	/**
	 * Capture post IDs from a tracked query loop block's WP_Query.
	 *
	 * Filter self-removes only when the matching sentinel query runs.
	 * Unrelated WP_Queries pass through untouched.
	 *
	 * @param array         $posts Posts returned by WP_Query.
	 * @param WP_Query|null $query The query instance.
	 *
	 * @return array Unchanged posts.
	 */
	public function capture_query_posts( array $posts, ?WP_Query $query = null ): array {

		if ( ! $query instanceof WP_Query || ! $query->get( self::CAPTURE_FLAG ) ) {
			return $posts;
		}

		remove_filter( 'the_posts', [ $this, 'capture_query_posts' ], 10 );

		foreach ( $posts as $post ) {
			if ( isset( $post->ID ) ) {
				Deduplicator::add( (int) $post->ID );
			}
		}

		return $posts;
	}

	/**
	 * Inject tracked IDs into queries flagged with the exclude_duplicates arg.
	 *
	 * @param WP_Query $query The query instance.
	 *
	 * @return void
	 */
	public function handle_exclude_duplicates_query_arg( WP_Query $query ): void {

		if ( ! $query->get( 'exclude_duplicates' ) ) {
			return;
		}

		$tracked = Deduplicator::get();

		if ( empty( $tracked ) ) {
			return;
		}

		$existing     = (array) $query->get( 'post__not_in' );
		$post__not_in = array_values( array_unique( array_merge( $existing, $tracked ) ) );

		$query->set( 'post__not_in', $post__not_in );
	}

	/**
	 * Determine whether a block's posts should be tracked.
	 *
	 * @param WP_Block $block Block instance.
	 *
	 * @return bool
	 */
	public function should_track( WP_Block $block ): bool {

		$query_id = $block->context['queryId'] ?? '';

		/**
		 * Filters whether a block's posts should be tracked for deduplication.
		 *
		 * Default true when any block on the page opts into deduplication.
		 *
		 * @param bool     $should_track Whether to track.
		 * @param string   $query_id     The block's queryId context.
		 * @param array    $attributes   The block's attributes.
		 * @param WP_Block $block        The block instance.
		 */
		return (bool) apply_filters(
			'outstand_query_loop_deduplication_should_track',
			$this->tracking_enabled,
			$query_id,
			$block->attributes,
			$block
		);
	}

	/**
	 * Determine whether tracked posts should be excluded from a block's query.
	 *
	 * @param WP_Block $block Block instance.
	 *
	 * @return bool
	 */
	public function should_exclude( WP_Block $block ): bool {

		$exclude_duplicates = $this->resolve_exclude_flag( $block );

		$query_id = $block->context['queryId']
			?? $block->attributes['queryId']
			?? '';

		/**
		 * Filters whether tracked posts should be excluded from a block's query.
		 *
		 * @param bool     $should_exclude Whether to exclude.
		 * @param string   $query_id       The block's queryId.
		 * @param array    $attributes     The block's attributes.
		 * @param WP_Block $block          The block instance.
		 */
		return (bool) apply_filters(
			'outstand_query_loop_deduplication_should_exclude',
			$exclude_duplicates,
			$query_id,
			$block->attributes,
			$block
		);
	}

	/**
	 * Track post IDs returned by a registered custom-block resolver.
	 *
	 * @param string   $block_content Block content.
	 * @param array    $parsed_block  Parsed block data.
	 * @param WP_Block $block         Block instance.
	 * @param callable $resolver      Resolver callable.
	 *
	 * @return string Unchanged content.
	 */
	private function track_resolved_post( string $block_content, array $parsed_block, WP_Block $block, callable $resolver ): string {

		if ( ! $this->should_track( $block ) ) {
			return $block_content;
		}

		$ids = $resolver( $parsed_block, $block );

		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				Deduplicator::add( $id );
			}
		}

		return $block_content;
	}

	/**
	 * Read the excludeDuplicates flag from block context (inner blocks) or attributes (core/query itself).
	 *
	 * @param WP_Block $block Block instance.
	 *
	 * @return bool
	 */
	private function resolve_exclude_flag( WP_Block $block ): bool {

		return (bool) (
			$block->context[ BlockAttributes::CONTEXT_KEY ]
				?? $block->attributes['excludeDuplicates']
				?? false
		);
	}

	/**
	 * Recursively check if a parsed block tree contains a dedup-enabled core/query.
	 *
	 * Follows `core/template-part` references so templates composed from parts
	 * are scanned as a single document.
	 *
	 * @param array                 $block Parsed block.
	 * @param array<string, true> $visited Visited template-part IDs, to guard cycles.
	 *
	 * @return bool
	 */
	private function has_dedup_block( array $block, array &$visited = [] ): bool {

		$name = $block['blockName'] ?? '';

		if ( 'core/query' === $name && ! empty( $block['attrs']['excludeDuplicates'] ) ) {
			return true;
		}

		if ( 'core/template-part' === $name ) {
			$slug  = (string) ( $block['attrs']['slug'] ?? '' );
			$theme = (string) ( $block['attrs']['theme'] ?? get_stylesheet() );

			if ( '' !== $slug && function_exists( 'get_block_template' ) ) {
				$id = $theme . '//' . $slug;

				if ( ! isset( $visited[ $id ] ) ) {
					$visited[ $id ] = true;
					$part           = get_block_template( $id, 'wp_template_part' );

					if ( $part && ! empty( $part->content ) ) {
						foreach ( parse_blocks( $part->content ) as $pb ) {
							if ( $this->has_dedup_block( $pb, $visited ) ) {
								return true;
							}
						}
					}
				}
			}
		}

		foreach ( $block['innerBlocks'] ?? [] as $inner ) {
			if ( $this->has_dedup_block( $inner, $visited ) ) {
				return true;
			}
		}

		return false;
	}
}
