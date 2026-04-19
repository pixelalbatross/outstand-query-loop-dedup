<?php

namespace Outstand\WP\QueryLoop\Dedup\Tests\Unit;

use Outstand\WP\QueryLoop\Dedup\Deduplicator;
use WP_Query;
use function Outstand\WP\QueryLoop\Dedup\Tests\create_posts;
use function Outstand\WP\QueryLoop\Dedup\Tests\make_block;

/**
 * Per-query tracking via capture sentinel + the_posts filter.
 *
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::setup_query_tracking
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::capture_query_posts
 * @covers \Outstand\WP\QueryLoop\Dedup\QueryDeduplication::should_track
 */
class TrackingCaptureTest extends QueryDeduplicationTestCase {

	public function test_setup_query_tracking_adds_sentinel_when_tracking_enabled(): void {
		$this->set_tracking_enabled( true );

		$block = make_block( 'core/post-template', [], [ 'queryId' => 1 ] );
		$query = $this->dedup->setup_query_tracking( [ 'post_type' => 'post' ], $block );

		$this->assertArrayHasKey( 'outstand_dedup_capture', $query );
		$this->assertTrue( $query['outstand_dedup_capture'] );
	}

	public function test_setup_query_tracking_noop_when_tracking_disabled(): void {
		$block = make_block( 'core/post-template', [], [ 'queryId' => 1 ] );
		$query = $this->dedup->setup_query_tracking( [ 'post_type' => 'post' ], $block );

		$this->assertArrayNotHasKey( 'outstand_dedup_capture', $query );
	}

	public function test_should_track_filter_override(): void {
		$this->set_tracking_enabled( true );

		add_filter( 'outstand_query_loop_deduplication_should_track', '__return_false' );

		$block = make_block( 'core/post-template', [], [ 'queryId' => 1 ] );
		$query = $this->dedup->setup_query_tracking( [], $block );

		$this->assertArrayNotHasKey( 'outstand_dedup_capture', $query );

		remove_filter( 'outstand_query_loop_deduplication_should_track', '__return_false' );
	}

	public function test_capture_query_posts_ignores_unflagged_query(): void {
		$query = new WP_Query( [ 'post_type' => 'post' ] );
		$posts = [ (object) [ 'ID' => 99 ] ];

		$this->dedup->capture_query_posts( $posts, $query );

		$this->assertFalse( Deduplicator::has( 99 ) );
	}

	public function test_capture_query_posts_captures_flagged_query(): void {
		$query = new WP_Query( [ 'post_type' => 'post' ] );
		$query->set( 'outstand_dedup_capture', true );

		$posts = [ (object) [ 'ID' => 1 ], (object) [ 'ID' => 2 ], (object) [ 'ID' => 3 ] ];
		$this->dedup->capture_query_posts( $posts, $query );

		$this->assertSame( [ 1, 2, 3 ], Deduplicator::get() );
	}

	public function test_end_to_end_capture_via_wp_query(): void {
		$ids = create_posts( 3 );
		$this->set_tracking_enabled( true );

		$block = make_block( 'core/post-template', [], [ 'queryId' => 1 ] );
		$args  = $this->dedup->setup_query_tracking(
			[
				'post_type'      => 'post',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			],
			$block
		);

		$query = new WP_Query( $args );

		$this->assertEqualsCanonicalizing( $ids, Deduplicator::get() );
	}

	private function set_tracking_enabled( bool $value ): void {
		$ref = new \ReflectionProperty( $this->dedup, 'tracking_enabled' );
		$ref->setAccessible( true );
		$ref->setValue( $this->dedup, $value );
	}
}
