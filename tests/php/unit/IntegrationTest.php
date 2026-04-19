<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

use Outstand\WP\QueryLoop\Dedup\Deduplicator;
use Outstand\WP\QueryLoop\Dedup\Plugin;
use Outstand\WP\QueryLoop\Dedup\QueryDeduplication;
use function Outstand\WP\QueryLoop\Dedup\Tests\create_posts;

/**
 * End-to-end integration via do_blocks() on a composed page.
 *
 * Uses the plugin instance registered on bootstrap (via Plugin::enable).
 */
class IntegrationTest extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Deduplicator::clear();
		$this->reset_tracking_flag();
	}

	protected function tearDown(): void {
		Deduplicator::clear();
		$this->reset_tracking_flag();
		parent::tearDown();
	}

	/**
	 * Reset the bootstrap QueryDeduplication instance's tracking_enabled flag.
	 *
	 * Detection state persists across tests because hooks are bound once on
	 * plugin bootstrap. Reflection gives us a clean slate per test without
	 * adding test-only setters to production code.
	 */
	private function reset_tracking_flag(): void {
		$module = Plugin::get_instance()->get_module( QueryDeduplication::class );
		if ( ! $module ) {
			return;
		}

		$ref = new \ReflectionProperty( $module, 'tracking_enabled' );
		$ref->setAccessible( true );
		$ref->setValue( $module, false );

		$backup = new \ReflectionProperty( $module, 'global_query_backup' );
		$backup->setAccessible( true );
		$backup->setValue( $module, [] );
	}

	public function test_two_query_loops_second_excludes_first(): void {
		$ids = create_posts( 6 );

		$content  = '<!-- wp:query {"queryId":1,"query":{"perPage":3,"postType":"post","orderBy":"date","order":"asc"}} -->';
		$content .= '<div class="wp-block-query">';
		$content .= '<!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template -->';
		$content .= '</div>';
		$content .= '<!-- /wp:query -->';

		$content .= '<!-- wp:query {"queryId":2,"query":{"perPage":3,"postType":"post","orderBy":"date","order":"asc"},"excludeDuplicates":true} -->';
		$content .= '<div class="wp-block-query">';
		$content .= '<!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template -->';
		$content .= '</div>';
		$content .= '<!-- /wp:query -->';

		$captured = $this->capture_rendered_ids( $content );

		$this->assertCount( 2, $captured, 'Two query loops should render.' );
		$this->assertCount( 3, $captured[0] );
		$this->assertCount( 3, $captured[1] );

		$overlap = array_intersect( $captured[0], $captured[1] );
		$this->assertEmpty( $overlap, 'Second loop must not repeat posts from the first.' );
	}

	public function test_single_flagged_query_tracks_but_has_nothing_to_exclude(): void {
		create_posts( 3 );

		$content  = '<!-- wp:query {"queryId":1,"query":{"perPage":3,"postType":"post"},"excludeDuplicates":true} -->';
		$content .= '<div class="wp-block-query">';
		$content .= '<!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template -->';
		$content .= '</div>';
		$content .= '<!-- /wp:query -->';

		$captured = $this->capture_rendered_ids( $content );

		$this->assertCount( 1, $captured );
		$this->assertCount( 3, $captured[0] );
		$this->assertSame( 3, Deduplicator::count() );
	}

	public function test_no_flagged_query_disables_tracking(): void {
		create_posts( 3 );

		$content  = '<!-- wp:query {"queryId":1,"query":{"perPage":3,"postType":"post"}} -->';
		$content .= '<div class="wp-block-query">';
		$content .= '<!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template -->';
		$content .= '</div>';
		$content .= '<!-- /wp:query -->';

		$this->capture_rendered_ids( $content );

		$this->assertSame( 0, Deduplicator::count(), 'No flag anywhere → registry stays empty.' );
	}

	/**
	 * Run do_blocks() on content, capturing each query-loop's rendered IDs.
	 *
	 * @param string $content Block markup.
	 *
	 * @return array<int, int[]>
	 */
	private function capture_rendered_ids( string $content ): array {
		$captured = [];

		$capture = static function ( array $posts, $query ) use ( &$captured ) {
			if ( $query && $query->get( 'outstand_dedup_capture' ) ) {
				$captured[] = array_map( static fn( $p ) => (int) $p->ID, $posts );
			}
			return $posts;
		};

		add_filter( 'the_posts', $capture, 100, 2 );

		// Trigger page-wide detection, then render.
		Plugin::get_instance()
			->get_module( QueryDeduplication::class )
			?->detect_tracking( $content );
		do_blocks( $content );

		remove_filter( 'the_posts', $capture, 100 );

		return $captured;
	}
}
